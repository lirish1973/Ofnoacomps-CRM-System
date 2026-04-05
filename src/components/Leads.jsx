import { useEffect, useState, useCallback } from 'react'
import { useApi } from '../hooks/useApi'

const STATUSES = { new:'חדש', contacted:'בטיפול', qualified:'מוסמך', converted:'הומר ללקוח', lost:'אבוד' }
const SOURCES  = { direct:'ישיר', google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
                   facebook_ads:'פייסבוק ממומן', facebook_organic:'פייסבוק', instagram:'אינסטגרם',
                   referral:'הפניה', email:'אימייל', whatsapp:'וואטסאפ', other:'אחר' }
const BADGE    = { new:'badge-new', contacted:'badge-contacted', qualified:'badge-qualified', converted:'badge-converted', lost:'badge-lost' }

export default function Leads({ site }) {
  const { get, patch, loading } = useApi(site.id)
  const [leads, setLeads]   = useState([])
  const [total, setTotal]   = useState(0)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [source, setSource] = useState('')
  const [page, setPage]     = useState(1)
  const [selected, setSelected] = useState(null)
  const PER = 25

  const load = useCallback(() => {
    const params = new URLSearchParams({
      limit: PER, offset: (page-1)*PER,
      ...(search && { search }),
      ...(status && { status }),
      ...(source && { source }),
    })
    get(`/leads?${params}`).then(r => {
      setLeads(r?.data || [])
      setTotal(r?.meta?.total || 0)
    }).catch(() => {})
  }, [site.id, search, status, source, page])

  useEffect(() => { load() }, [load])

  const changeStatus = async (id, newStatus) => {
    await patch(`/leads/${id}`, { status: newStatus })
    setLeads(prev => prev.map(l => l.id === id ? {...l, status: newStatus} : l))
  }

  const pages = Math.ceil(total / PER)

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold text-slate-800">לידים <span className="text-slate-400 text-base font-normal">({total})</span></h2>
        {loading && <span className="text-xs text-slate-400 animate-pulse">טוען...</span>}
      </div>

      {/* Filters */}
      <div className="card flex flex-wrap gap-3 items-center">
        <input
          type="search" placeholder="חיפוש שם, אימייל, טלפון..."
          value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
          className="border border-slate-200 rounded-lg px-3 py-2 text-sm w-56 focus:outline-none focus:border-blue-400"
        />
        <select value={status} onChange={e => { setStatus(e.target.value); setPage(1) }}
          className="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
          <option value="">כל הסטטוסים</option>
          {Object.entries(STATUSES).map(([k,v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select value={source} onChange={e => { setSource(e.target.value); setPage(1) }}
          className="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
          <option value="">כל המקורות</option>
          {Object.entries(SOURCES).map(([k,v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        {(search||status||source) && (
          <button onClick={() => { setSearch(''); setStatus(''); setSource(''); setPage(1) }}
            className="text-xs text-slate-500 hover:text-red-500 transition">נקה פילטרים ✕</button>
        )}
      </div>

      {/* Table */}
      <div className="card overflow-hidden p-0">
        <div className="overflow-x-auto">
          <table className="crm-table">
            <thead><tr>
              <th>שם</th><th>אימייל</th><th>טלפון</th><th>מקור</th><th>קמפיין</th>
              <th>ציון</th><th>סטטוס</th><th>תאריך</th>
            </tr></thead>
            <tbody>
            {leads.map(l => (
              <tr key={l.id} className="cursor-pointer" onClick={() => setSelected(l)}>
                <td className="font-medium text-slate-800">
                  {[l.first_name, l.last_name].filter(Boolean).join(' ') || <span className="text-slate-400">(ללא שם)</span>}
                </td>
                <td className="text-slate-600">{l.email || '—'}</td>
                <td className="text-slate-600 whitespace-nowrap">{l.phone || '—'}</td>
                <td><span className={`source-chip source-${l.source}`}>{SOURCES[l.source] || l.source}</span></td>
                <td className="text-slate-400 text-xs max-w-[120px] truncate">{l.campaign || '—'}</td>
                <td>
                  <div className="flex items-center gap-2">
                    <div className="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div className="h-full bg-blue-500 rounded-full" style={{width:`${l.score}%`}}/>
                    </div>
                    <span className="text-xs text-slate-500">{l.score}</span>
                  </div>
                </td>
                <td onClick={e => e.stopPropagation()}>
                  <select
                    value={l.status}
                    onChange={e => changeStatus(l.id, e.target.value)}
                    className={`text-xs px-2 py-1 rounded-lg border-0 font-semibold cursor-pointer focus:outline-none ${
                      l.status === 'new' ? 'bg-blue-100 text-blue-800' :
                      l.status === 'contacted' ? 'bg-yellow-100 text-yellow-800' :
                      l.status === 'qualified' ? 'bg-green-100 text-green-800' :
                      l.status === 'converted' ? 'bg-emerald-100 text-emerald-800' :
                      'bg-red-100 text-red-800'
                    }`}
                  >
                    {Object.entries(STATUSES).map(([k,v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                </td>
                <td className="text-slate-400 text-xs whitespace-nowrap">
                  {new Date(l.created_at).toLocaleDateString('he-IL')}
                </td>
              </tr>
            ))}
            {!leads.length && !loading && (
              <tr><td colSpan={8} className="text-center text-slate-400 py-12">אין לידים</td></tr>
            )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {pages > 1 && (
        <div className="flex gap-2 justify-center">
          {Array.from({length: Math.min(pages,10)}, (_,i) => i+1).map(p => (
            <button key={p} onClick={() => setPage(p)}
              className={`w-8 h-8 rounded-lg text-sm font-medium transition ${
                p === page ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
              }`}>{p}</button>
          ))}
        </div>
      )}

      {/* Lead Detail Drawer */}
      {selected && <LeadDrawer lead={selected} site={site} onClose={() => setSelected(null)} onRefresh={load} />}
    </div>
  )
}

function LeadDrawer({ lead, site, onClose, onRefresh }) {
  const { get, post, patch } = useApi(site.id)
  const [activities, setActs] = useState([])
  const [actType, setActType] = useState('note')
  const [actBody, setActBody] = useState('')

  useEffect(() => {
    get(`/leads/${lead.id}/activities`).then(r => setActs(r?.data || [])).catch(() => {})
  }, [lead.id])

  const addActivity = async () => {
    if (!actBody.trim()) return
    await post(`/leads/${lead.id}/activities`, { type: actType, subject: actType, body: actBody })
    setActBody('')
    const r = await get(`/leads/${lead.id}/activities`)
    setActs(r?.data || [])
  }

  const convert = async () => {
    if (!confirm('להמיר ליד ללקוח?')) return
    await post(`/leads/${lead.id}/convert`, {})
    onRefresh(); onClose()
  }

  const ACT_ICONS = { note:'📝', call:'📞', email:'✉️', meeting:'🤝', task:'✅', sms:'💬', whatsapp:'📱' }
  const SOURCES_HE = { direct:'ישיר', google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
    facebook_ads:'פייסבוק ממומן', instagram:'אינסטגרם', referral:'הפניה', email:'אימייל', whatsapp:'וואטסאפ' }

  return (
    <div className="fixed inset-0 z-50 flex">
      <div className="flex-1 bg-black/40" onClick={onClose}/>
      <div className="w-[480px] bg-white h-full overflow-y-auto shadow-2xl flex flex-col" dir="rtl">
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 className="font-bold text-slate-900">{[lead.first_name, lead.last_name].filter(Boolean).join(' ') || '(ללא שם)'}</h2>
            <div className="text-xs text-slate-400">{lead.email}</div>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-xl">✕</button>
        </div>

        <div className="flex-1 p-6 space-y-5">
          {/* Contact info */}
          <div className="space-y-2">
            <InfoRow label="אימייל"   value={lead.email}  link={`mailto:${lead.email}`} />
            <InfoRow label="טלפון"    value={lead.phone}  link={`tel:${lead.phone}`} />
            <InfoRow label="מקור"     value={SOURCES_HE[lead.source] || lead.source} />
            <InfoRow label="קמפיין"   value={lead.campaign || '—'} />
            <InfoRow label="מילת מפתח" value={lead.utm_term || '—'} />
            <InfoRow label="Landing"  value={lead.landing_page} link={lead.landing_page} />
            <InfoRow label="מכשיר"   value={lead.device_type || '—'} />
            <InfoRow label="הודעה"   value={lead.message || '—'} />
          </div>

          {/* Score */}
          <div className="flex items-center gap-3">
            <span className="text-sm text-slate-500">ציון ליד:</span>
            <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
              <div className="h-full bg-blue-500 rounded-full transition-all" style={{width:`${lead.score}%`}}/>
            </div>
            <span className="font-bold text-slate-700">{lead.score}/100</span>
          </div>

          {/* Actions */}
          {lead.status !== 'converted' && (
            <button onClick={convert}
              className="w-full bg-green-600 hover:bg-green-700 text-white rounded-xl py-2.5 font-semibold text-sm transition">
              המר ללקוח →
            </button>
          )}

          {/* Activities */}
          <div>
            <h3 className="font-semibold text-slate-700 mb-3">פעילות</h3>
            <div className="space-y-2 mb-4">
              {activities.map(a => (
                <div key={a.id} className="flex gap-2 text-sm">
                  <span className="mt-0.5">{ACT_ICONS[a.type] || '📌'}</span>
                  <div>
                    <div className="text-slate-700">{a.body || a.subject}</div>
                    <div className="text-xs text-slate-400">{new Date(a.created_at).toLocaleString('he-IL')}</div>
                  </div>
                </div>
              ))}
              {!activities.length && <div className="text-slate-400 text-xs">אין פעילויות עדיין</div>}
            </div>

            {/* Add activity */}
            <div className="space-y-2">
              <select value={actType} onChange={e => setActType(e.target.value)}
                className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                {Object.entries(ACT_ICONS).map(([k]) => <option key={k} value={k}>{ACT_ICONS[k]} {k}</option>)}
              </select>
              <textarea value={actBody} onChange={e => setActBody(e.target.value)}
                placeholder="תוכן הפעילות..."
                className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm h-20 resize-none focus:outline-none focus:border-blue-400"
              />
              <button onClick={addActivity}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2 text-sm font-semibold transition">
                + הוסף פעילות
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function InfoRow({ label, value, link }) {
  return (
    <div className="flex justify-between text-sm">
      <span className="text-slate-400 font-medium min-w-[90px]">{label}</span>
      <span className="text-slate-700 text-left">
        {link && value ? <a href={link} target="_blank" rel="noreferrer" className="text-blue-500 hover:underline truncate max-w-[240px] block">{value}</a> : (value || '—')}
      </span>
    </div>
  )
}
