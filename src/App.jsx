import { useState, useEffect } from 'react'
import Sidebar from './components/Sidebar'
import SiteView from './components/SiteView'
import SiteSettings from './components/SiteSettings'

export default function App() {
  const [sites, setSites]         = useState([])
  const [activeSite, setActiveSite] = useState(null)
  const [showAdd, setShowAdd]     = useState(false)
  const [globalLoading, setGL]    = useState(true)

  // Load sites from server on boot
  useEffect(() => {
    fetch('/api/sites')
      .then(r => r.json())
      .then(data => {
        setSites(data)
        if (data.length) setActiveSite(data[0].id)
      })
      .catch(() => {})
      .finally(() => setGL(false))
  }, [])

  const addSite = async (siteData) => {
    const res  = await fetch('/api/sites', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(siteData),
    })
    const site = await res.json()
    setSites(prev => [...prev, site])
    setActiveSite(site.id)
    setShowAdd(false)
  }

  const removeSite = async (id) => {
    await fetch(`/api/sites/${id}`, { method: 'DELETE' })
    const updated = sites.filter(s => s.id !== id)
    setSites(updated)
    setActiveSite(updated.length ? updated[0].id : null)
  }

  const updateSite = async (id, data) => {
    const res  = await fetch(`/api/sites/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
    const site = await res.json()
    setSites(prev => prev.map(s => s.id === id ? site : s))
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
    <div className="flex h-screen bg-slate-50 overflow-hidden">
      {/* Sidebar */}
      <Sidebar
        sites={sites}
        activeSite={activeSite}
        onSelect={setActiveSite}
        onAdd={() => setShowAdd(true)}
        onRemove={removeSite}
      />

      {/* Main content */}
      <div className="flex-1 overflow-hidden flex flex-col">
        {currentSite ? (
          <SiteView site={currentSite} onUpdateSite={updateSite} />
        ) : (
          <EmptyState onAdd={() => setShowAdd(true)} />
        )}
      </div>

      {/* Add Site Modal */}
      {showAdd && (
        <SiteSettings
          mode="add"
          onSave={addSite}
          onClose={() => setShowAdd(false)}
        />
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
      <button
        onClick={onAdd}
        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold transition"
      >
        + הוסף אתר
      </button>
    </div>
  )
}
