import { useState } from 'react'
import Dashboard from './Dashboard'
import Leads from './Leads'
import Customers from './Customers'
import Pipeline from './Pipeline'
import Reports from './Reports'
import SiteSettings from './SiteSettings'
import Analytics from './Analytics'

const TABS = [
  { id: 'dashboard',  label: 'דשבורד',   icon: '📊' },
  { id: 'leads',      label: 'לידים',     icon: '👥' },
  { id: 'customers',  label: 'לקוחות',    icon: '🏢' },
  { id: 'pipeline',   label: 'Pipeline',  icon: '🔄' },
  { id: 'reports',    label: 'דוחות',     icon: '📈' },
  { id: 'analytics',  label: 'אנליטיקס',  icon: '📊' },
  { id: 'settings',   label: 'הגדרות',    icon: '⚙️' },
]

export default function SiteView({ site, onUpdateSite }) {
  const [activeTab, setActiveTab] = useState('dashboard')

  const renderTab = () => {
    switch (activeTab) {
      case 'dashboard': return <Dashboard site={site} />
      case 'leads':     return <Leads site={site} />
      case 'customers': return <Customers site={site} />
      case 'pipeline':  return <Pipeline site={site} />
      case 'reports':   return <Reports site={site} />
      case 'analytics': return <Analytics site={site} />
      case 'settings':  return <SiteSettings mode="edit" site={site} onSave={d => onUpdateSite(site.id, d)} />
      default:          return null
    }
  }

  return (
    <div className="flex flex-col h-full" dir="rtl">
      {/* Site header + tabs */}
      <div className="bg-white border-b border-slate-200 px-6 pt-4 flex-shrink-0">
        <div className="flex items-center gap-3 mb-3">
          <div className="flex items-center gap-2">
            <span className="text-xl">🌐</span>
            <div>
              <h1 className="font-bold text-slate-900 text-lg leading-tight">{site.name}</h1>
              <a href={site.url} target="_blank" rel="noreferrer"
                className="text-xs text-blue-500 hover:underline">
                {site.url}
              </a>
            </div>
          </div>
          <div className={`mr-auto flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full ${
            site.status === 'online' ? 'bg-green-100 text-green-700' :
            site.status === 'error'  ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'
          }`}>
            <span className={`w-1.5 h-1.5 rounded-full ${
              site.status === 'online' ? 'bg-green-500' :
              site.status === 'error'  ? 'bg-red-500' : 'bg-slate-400'
            }`}/>
            {site.status === 'online' ? 'מחובר' : site.status === 'error' ? 'שגיאה' : 'לא ידוע'}
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-1">
          {TABS.map(t => (
            <button
              key={t.id}
              onClick={() => setActiveTab(t.id)}
              className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition ${
                activeTab === t.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
              }`}
            >
              <span>{t.icon}</span>
              <span>{t.label}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Tab content */}
      <div className="flex-1 overflow-y-auto">
        {renderTab()}
      </div>
    </div>
  )
}
