import { useState, useEffect } from 'react'
import axios from 'axios'
import Sidebar from './components/Sidebar'
import SiteView from './components/SiteView'
import SiteSettings from './components/SiteSettings'
import MultiSiteDashboard from './components/MultiSiteDashboard'

export default function App() {
  const [sites, setSites]           = useState([])
  const [activeSite, setActiveSite] = useState(null)   // null = show dashboard
  const [showAdd, setShowAdd]       = useState(false)
  const [globalLoading, setGL]      = useState(true)

  useEffect(() => {
    axios.get('/api/sites')
      .then(r => { setSites(r.data) })
      .catch(() => {})
      .finally(() => setGL(false))
  }, [])

  // Poll site statuses every 30s
  useEffect(() => {
    const t = setInterval(() => {
      axios.get('/api/sites').then(r => setSites(r.data)).catch(() => {})
    }, 30_000)
    return () => clearInterval(t)
  }, [])

  const addSite = async (siteData) => {
    const res  = await axios.post('/api/sites', siteData)
    setSites(prev => [...prev, res.data])
    setActiveSite(res.data.id)
    setShowAdd(false)
  }

  const removeSite = async (id) => {
    await axios.delete(`/api/sites/${id}`)
    const updated = sites.filter(s => s.id !== id)
    setSites(updated)
    if (activeSite === id) setActiveSite(null)
  }

  const updateSite = async (id, data) => {
    const res = await axios.patch(`/api/sites/${id}`, data)
    setSites(prev => prev.map(s => s.id === id ? res.data : s))
  }

  const currentSite = sites.find(s => s.id === activeSite)

  if (globalLoading) return (
    <div className="h-screen flex items-center justify-center bg-slate-50">
      <div className="text-center">
        <div className="text-4xl mb-3">🏢</div>
        <div className="text-slate-500 text-sm">טוען Ofnoacomps CRM...</div>
      </div>
    </div>
  )

  return (
    <div className="flex h-screen bg-slate-50 overflow-hidden" dir="rtl">
      {/* Sidebar */}
      <Sidebar
        sites={sites}
        activeSite={activeSite}
        onSelect={setActiveSite}
        onHome={() => setActiveSite(null)}
        onAdd={() => setShowAdd(true)}
        onRemove={removeSite}
      />

      {/* Main content */}
      <div className="flex-1 overflow-auto flex flex-col">
        {activeSite && currentSite ? (
          <SiteView site={currentSite} onUpdateSite={updateSite} />
        ) : sites.length > 0 ? (
          <MultiSiteDashboard
            sites={sites}
            onSelectSite={site => setActiveSite(site.id)}
            onAddSite={() => setShowAdd(true)}
          />
        ) : (
          <EmptyState onAdd={() => setShowAdd(true)} />
        )}
      </div>

      {/* Add Site Modal */}
      {showAdd && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" dir="rtl">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
            <div className="flex items-center justify-between mb-5">
              <h2 className="text-lg font-bold text-gray-900">הוסף אתר חדש</h2>
              <button onClick={() => setShowAdd(false)} className="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
            <SiteSettings onSave={addSite} onCancel={() => setShowAdd(false)} />
          </div>
        </div>
      )}
    </div>
  )
}

function EmptyState({ onAdd }) {
  return (
    <div className="flex-1 flex flex-col items-center justify-center text-center p-12">
      <div className="text-7xl mb-6">🌐</div>
      <h2 className="text-2xl font-bold text-slate-800 mb-3">ברוכים הבאים ל-Ofnoacomps CRM</h2>
      <p className="text-slate-500 mb-8 max-w-md">
        מערכת ניהול לקוחות מרכזית לכל האתרים שלך.<br/>
        הוסף את האתר הראשון כדי להתחיל.
      </p>
      <button onClick={onAdd}
        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold transition">
        + הוסף אתר
      </button>
    </div>
  )
}
