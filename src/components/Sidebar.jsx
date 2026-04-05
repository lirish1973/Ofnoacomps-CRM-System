import { useState } from 'react'

const COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']

export default function Sidebar({ sites, activeSite, onSelect, onAdd, onRemove }) {
  const [hoverId, setHoverId] = useState(null)

  return (
    <div className="w-64 bg-slate-900 text-white flex flex-col h-screen flex-shrink-0" dir="rtl">
      {/* Logo */}
      <div className="px-5 py-5 border-b border-slate-700">
        <div className="flex items-center gap-3">
          <span className="text-2xl">🏢</span>
          <div>
            <div className="font-bold text-sm leading-tight">Ofnoacomps</div>
            <div className="text-xs text-slate-400">CRM System</div>
          </div>
        </div>
      </div>

      {/* Sites list */}
      <div className="flex-1 overflow-y-auto py-3">
        <div className="px-4 mb-2">
          <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider">אתרים</span>
        </div>

        {sites.map((site, i) => {
          const color = COLORS[i % COLORS.length]
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
              {/* Site avatar */}
              <div
                className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                style={{ background: color }}
              >
                {site.name?.[0]?.toUpperCase() || '?'}
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

              {/* Remove btn */}
              {hoverId === site.id && (
                <button
                  onClick={e => { e.stopPropagation(); if(confirm('הסר אתר?')) onRemove(site.id) }}
                  className="text-slate-400 hover:text-red-400 transition text-xs px-1"
                  title="הסר"
                >✕</button>
              )}
            </div>
          )
        })}

        {/* Add site button */}
        <button
          onClick={onAdd}
          className="w-full flex items-center gap-3 mx-2 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition text-sm"
          style={{ width: 'calc(100% - 16px)' }}
        >
          <div className="w-8 h-8 rounded-lg border-2 border-dashed border-slate-600 flex items-center justify-center text-lg">+</div>
          <span>הוסף אתר</span>
        </button>
      </div>

      {/* Footer */}
      <div className="px-5 py-4 border-t border-slate-700">
        <div className="text-xs text-slate-500">
          {sites.length} אתר{sites.length !== 1 ? 'ים' : ''} מחוברים
        </div>
        <div className="text-xs text-slate-600 mt-0.5">v1.0.0</div>
      </div>
    </div>
  )
}
