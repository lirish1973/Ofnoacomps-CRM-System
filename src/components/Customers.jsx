import { useEffect, useState, useCallback } from 'react'
import { useApi } from '../hooks/useApi'

export default function Customers({ site }) {
  const { get, loading } = useApi(site.id)
  const [customers, setC] = useState([])
  const [total, setTotal] = useState(0)
  const [search, setSrch] = useState('')
  const [page, setPage]   = useState(1)
  const [selected, setSel] = useState(null)
  const PER = 25

  const load = useCallback(() => {
    const params = new URLSearchParams({ limit: PER, offset: (page-1)*PER, ...(search && { search }) })
    get(`/customers?${params}`).then(r => { setC(r?.data || []); setTotal(r?.meta?.total || 0) }).catch(() => {})
  }, [site.id, search, page])

  useEffect(() => { load() }, [load])

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold text-slate-800">לקוחות <span className="text-slate-400 text-base font-normal">({total})</span></h2>
        {loading && <span className="text-xs text-slate-400 animate-pulse">טוען...</span>}
      </div>

      <div className="card flex gap-3">
        <input type="search" placeholder="חיפוש שם, אימייל, חברה..."
          value={search} onChange={e => { setSrch(e.target.value); setPage(1) }}
          className="border border-slate-200 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:border-blue-400" />
      </div>

      <div className="card overflow-hidden p-0">
        <div className="overflow-x-auto">
          <table className="crm-table">
            <thead><tr><th>שם</th><th>חברה</th><th>אימייל / טלפון</th><th>מקור</th><th>סטטוס</th><th>הצטרף</th></tr></thead>
            <tbody>
            {customers.map(c => (
              <tr key={c.id} className="cursor-pointer" onClick={() => setSel(c)}>
                <td className="font-medium text-slate-800">{[c.first_name, c.last_name].filter(Boolean).join(' ') || '(ללא שם)'}</td>
                <td className="text-slate-600">{c.company || '—'}</td>
                <td>
                  <div className="text-slate-700">{c.email}</div>
                  <div className="text-slate-400 text-xs">{c.phone}</div>
                </td>
                <td><span className={`source-chip source-${c.source}`}>{c.source || '—'}</span></td>
                <td><span className={`badge ${c.status === 'active' ? 'badge-qualified' : 'badge-lost'}`}>{c.status === 'active' ? 'פעיל' : 'לא פעיל'}</span></td>
                <td className="text-slate-400 text-xs">{new Date(c.created_at).toLocaleDateString('he-IL')}</td>
              </tr>
            ))}
            {!customers.length && !loading && (
              <tr><td colSpan={6} className="text-center text-slate-400 py-12">אין לקוחות עדיין</td></tr>
            )}
            </tbody>
          </table>
        </div>
      </div>

      {Math.ceil(total/PER) > 1 && (
        <div className="flex gap-2 justify-center">
          {Array.from({length: Math.min(Math.ceil(total/PER), 10)}, (_,i) => i+1).map(p => (
            <button key={p} onClick={() => setPage(p)}
              className={`w-8 h-8 rounded-lg text-sm font-medium transition ${p === page ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}`}>{p}</button>
          ))}
        </div>
      )}

      {selected && (
        <CustomerDrawer customer={selected} site={site} onClose={() => setSel(null)} />
      )}
    </div>
  )
}

function CustomerDrawer({ customer, site, onClose }) {
  const { get } = useApi(site.id)
  const [activities, setActs]  = useState([])
  const [deals, setDeals]      = useState([])

  useEffect(() => {
    get(`/customers/${customer.id}/activities`).then(r => setActs(r?.data || [])).catch(() => {})
    get(`/customers/${customer.id}/deals`).then(r => setDeals(r?.data || [])).catch(() => {})
  }, [customer.id])

  const cur = site.currency || '₪'

  return (
    <div className="fixed inset-0 z-50 flex">
      <div className="flex-1 bg-black/40" onClick={onClose}/>
      <div className="w-[480px] bg-white h-full overflow-y-auto shadow-2xl" dir="rtl">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 className="font-bold text-slate-900">{[customer.first_name, customer.last_name].filter(Boolean).join(' ')}</h2>
            <div className="text-xs text-slate-400">{customer.company}</div>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-xl">✕</button>
        </div>
        <div className="p-6 space-y-5">
          <div className="space-y-2 text-sm">
            {customer.email && <div className="flex justify-between"><span className="text-slate-400">אימייל</span><a href={`mailto:${customer.email}`} className="text-blue-500">{customer.email}</a></div>}
            {customer.phone && <div className="flex justify-between"><span className="text-slate-400">טלפון</span><a href={`tel:${customer.phone}`} className="text-blue-500">{customer.phone}</a></div>}
            {customer.city  && <div className="flex justify-between"><span className="text-slate-400">עיר</span><span>{customer.city}</span></div>}
            {customer.notes && <div className="flex justify-between"><span className="text-slate-400">הערות</span><span>{customer.notes}</span></div>}
          </div>

          {deals.length > 0 && (
            <div>
              <h3 className="font-semibold text-slate-700 mb-2">עסקאות ({deals.length})</h3>
              <div className="space-y-2">
                {deals.map(d => (
                  <div key={d.id} className="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2 text-sm">
                    <span className="text-slate-700">{d.name}</span>
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-800">{cur}{Number(d.amount||0).toLocaleString()}</span>
                      <span className={`badge ${d.status === 'won' ? 'badge-won' : d.status === 'lost' ? 'badge-lost' : 'badge-open'}`}>
                        {d.status === 'won' ? 'זכייה' : d.status === 'lost' ? 'הפסד' : 'פתוח'}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activities.length > 0 && (
            <div>
              <h3 className="font-semibold text-slate-700 mb-2">פעילות אחרונה</h3>
              {activities.slice(0,5).map(a => (
                <div key={a.id} className="text-sm text-slate-600 py-1.5 border-b border-slate-100">
                  <div>{a.body || a.subject}</div>
                  <div className="text-xs text-slate-400">{new Date(a.created_at).toLocaleString('he-IL')}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
