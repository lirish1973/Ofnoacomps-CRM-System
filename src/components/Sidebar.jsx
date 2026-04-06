import { useState } from 'react'

const COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']

function initials(name = '') {
  return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() || '?'
}

export default function Sidebar({ sites, activeSite, onSelect, onHome, onAdd, onRemove }) {
  const [hoverId, setHoverId] = useState(null)
  const isHome = activeSite === null

  return (
    <div className="w-64 bg-slate-900 text-white flex flex-col h-screen flex-shrink-0" dir="rtl">
      {/* Logo / Home button */}
      <div
        className={`px-5 py-5 border-b border-slate-700 cursor-pointer transition ${isHome ? 'bg-slate-800' : 'hover:bg-slate-800'}`}
        onClick={onHome}
        title="דשבורד ראשי"
      >
        <div className="flex items-center gap-3">
          <span className="text-2xl">🏢</span>
          <div>
            <div className="font-bold text-sm leading-tight">Ofnoacomps</div>
            <div className="text-xs text-slate-400">CRM System</div>
          </div>
          {isHome && <span className="mr-auto text-blue-400 text-xs">●</span>}
        </div>
      </div>

      {/* Sites list */}
      <div className="flex-1 overflow-y-auto py-3">
        <div className="px-4 mb-2">
          <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider">אתרים</span>
        </div>

        {sites.map((site, i) => {
          const color    = COLORS[i % COLORS.length]
          const isActive = site.id === activeSite
          return (
            <div
              key={site.id}
              className={`group flex items-center gap-3 mx-2 px-3 py-2.5 rounded-lg cursor-pointer transition mb-1 ${
                isActive ? 'bg-slate-700' : 'hover:bg-slate-800'
              }`}
              onClick={() => onSelect(site.id)}
              onMouseEnter={() => setHoverId(site.id)}
              onMouseLeave={() => setHoverId(null)}
            >
              {/* Avatar */}
              <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                style={{ background: color }}>
                {initials(site.name)}
              </div>

              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium truncate">{site.name}</div>
                <div className="text-xs text-slate-400 truncate">{site.url?.replace(/https?:\/\//, '')}</div>
              </div>

              {/* Status dot */}
              <div className={`w-2 h-2 rounded-full flex-shrink-0 ${
                site.status === 'online' ? 'bg-green-400' :
                site.status === 'error'  ? 'bg-red-400'   : 'bg-slate-500'
              }`} />

              {/* Remove btn on hover */}
              {hoverId === site.id && (
                <button
                  onClick={e => { e.stopPropagation(); if (confirm('הסר אתר?')) onRemove(site.id) }}
                  className="text-slate-400 hover:text-red-400 transition text-xs px-1"
                  title="הסר"
                >✕</button>
              )}
            </div>
          )
        })}

        {/* Add site */}
        <button
          onClick={onAdd}
          className="flex items-center gap-3 mx-2 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition text-sm"
          style={{ width: 'calc(100% - 16px)' }}
        >
          <div className="w-8 h-8 rounded-lg border-2 border-dashed border-slate-600 flex items-center justify-center text-lg">+</div>
          <span>הוסף אתר</span>
        </button>
      </div>

      {/* Footer */}
      <div className="px-5 py-4 border-t border-slate-700">
        <div className="flex items-center gap-2 text-xs text-slate-500">
          <span className="w-2 h-2 rounded-full bg-green-400"/>
          {sites.filter(s => s.status === 'online').length} פעילים
          <span className="mx-1">·</span>
          {sites.length} סה"כ
        </div>
        <div className="text-xs text-slate-600 mt-0.5">v1.1.0</div>
      </div>
    </div>
  )
}
