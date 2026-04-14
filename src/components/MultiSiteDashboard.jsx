import { useState, useEffect, useCallback } from 'react'
import axios from 'axios'
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from 'recharts'

const AVATAR_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16']
const SOURCE_COLORS = {
  google_organic:'#34a853', google_ads:'#fbbc05',
  facebook_ads:'#1877f2',   instagram:'#e1306c',
  direct:'#6b7280',         referral:'#8b5cf6', other:'#94a3b8',
}
const SOURCE_LABELS = {
  google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
  facebook_ads:'פייסבוק',       instagram:'אינסטגרם',
  direct:'ישיר',                referral:'הפניה', other:'אחר',
}

function initials(name = '') {
  return (name.split(/\s+/).map(w => w[0]).join('').slice(0, 2) || '?').toUpperCase()
}
function unwrap(res) { return res?.data ?? res }

function trendPct(curr, prev) {
  const c = Number(curr || 0), p = Number(prev || 0)
  if (p === 0) return null
  return Math.round(((c - p) / p) * 100)
}

function TrendPill({ curr, prev }) {
  const pct = trendPct(curr, prev)
  if (pct === null || isNaN(pct)) return null
  const up = pct >= 0
  return (
    <span className={`text-xs font-bold ${up ? 'text-green-600' : 'text-red-500'}`}>
      {up ? '↑' : '↓'}{Math.abs(pct)}%
    </span>
  )
}

// ── Single site card ──────────────────────────────────────────────────────────
function SiteCard({ site, colorIdx, onSelect }) {
  const [summary,  setSummary]  = useState(null)
  const [prevSum,  setPrevSum]  = useState(null)
  const [analytics,setAnalytics]= useState(null)
  const [sources,  setSources]  = useState([])
  const [loading,  setLoading]  = useState(true)
  const [error,    setError]    = useState(null)

  const color = AVATAR_COLORS[colorIdx % AVATAR_COLORS.length]
  const days  = 30

  const fetchData = useCallback(async () => {
    setLoading(true); setError(null)
    const now    = new Date()
    const toD    = now.toISOString().slice(0, 10)
    const fromD  = new Date(+now - days * 86400000).toISOString().slice(0, 10)
    const pFromD = new Date(+now - days * 2 * 86400000).toISOString().slice(0, 10)
    const base   = `/api/sites/${site.id}/proxy`

    try {
      const r = await axios.get(`${base}/reports/summary?from=${fromD}&to=${toD}`)
      setSummary(unwrap(r.data))
    } catch (e) {
      setError((e.response?.data?.error || e.message || 'שגיאה').replace(/<[^>]*>/g,' ').slice(0,120))
      setLoading(false); return
    }

    // Optional calls — don't block card
    await Promise.allSettled([
      axios.get(`${base}/reports/summary?from=${pFromD}&to=${fromD}`)
        .then(r => setPrevSum(unwrap(r.data))),
      axios.get(`${base}/analytics/summary?date_from=${fromD}&date_to=${toD}`)
        .then(r => setAnalytics(unwrap(r.data))),
      axios.get(`${base}/reports/leads-by-source?from=${fromD}&to=${toD}`)
        .then(r => {
          const rows = unwrap(r.data)
          if (Array.isArray(rows)) setSources(rows.map(row => ({
            name:  SOURCE_LABELS[row.source] || row.source,
            value: Number(row.count ?? row.total ?? 0),
            color: SOURCE_COLORS[row.source] || '#94a3b8',
          })).filter(r => r.value > 0))
        }),
    ])

    setLoading(false)
  }, [site.id])

  useEffect(() => { fetchData() }, [fetchData])

  const S  = summary  || {}
  const P  = prevSum  || {}
  const A  = analytics || {}
  const cur = site.currency || '₪'

  const kpis = summary ? [
    { label:'לידים חודש',   value: S.leads_period,      prev: P.leads_period,      icon:'🎯', color:'#3b82f6' },
    { label:'לקוחות',        value: S.customers_active,  prev: P.customers_active,  icon:'👥', color:'#10b981' },
    { label:'Pipeline',      value:`${cur}${Number(S.deals_open_amount||0).toLocaleString()}`,
      prev: P.deals_open_amount,  icon:'🤝', color:'#f59e0b', isAmount:true },
    { label:'הכנסות',         value:`${cur}${Number(S.deals_won_period||0).toLocaleString()}`,
      prev: P.deals_won_period,   icon:'💰', color:'#8b5cf6', isAmount:true },
  ] : []

  return (
    <div onClick={() => onSelect(site)}
      className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all cursor-pointer flex flex-col">

      {/* Header */}
      <div className="flex items-center gap-3 p-5 border-b border-gray-50" dir="rtl">
        <div className="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
          style={{ background: color }}>
          {initials(site.name)}
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-gray-900 text-base truncate">{site.name}</h3>
          <p className="text-xs text-gray-400 truncate">{site.url}</p>
        </div>
        <span className={`flex-shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium ${
          site.status === 'online' ? 'bg-green-50 text-green-600' :
          site.status === 'error'  ? 'bg-red-50 text-red-500'     : 'bg-gray-100 text-gray-400'
        }`}>
          {site.status === 'online' ? '● מחובר' : site.status === 'error' ? '● לא מגיב' : '● בודק...'}
        </span>
      </div>

      {/* Body */}
      <div className="flex-1 p-5">
        {loading && (
          <div className="flex items-center justify-center h-28 text-gray-400 text-sm">
            <span className="animate-pulse">טוען נתונים...</span>
          </div>
        )}
        {!loading && error && (
          <div className="flex flex-col items-center justify-center h-28 gap-2 text-center" dir="rtl">
            <span className="text-3xl">🔌</span>
            <p className="text-sm text-amber-600 font-medium">נדרשת הגדרת חיבור</p>
            <p className="text-xs text-gray-400 max-w-[200px]">{error}</p>
          </div>
        )}
        {!loading && !error && summary && (
          <div className="space-y-4" dir="rtl">
            {/* CRM KPIs 2x2 */}
            <div className="grid grid-cols-2 gap-2.5">
              {kpis.map((k, i) => (
                <div key={i} className="bg-gray-50 rounded-xl p-3 text-right">
                  <div className="flex items-start justify-between mb-1">
                    <TrendPill curr={k.isAmount ? Number(k.value?.replace?.(/[^0-9.]/g,'') || 0) : k.value} prev={k.prev} />
                    <span className="text-base">{k.icon}</span>
                  </div>
                  <div className="text-lg font-bold" style={{ color: k.color }}>{k.value ?? '—'}</div>
                  <div className="text-xs text-gray-500 mt-0.5">{k.label}</div>
                </div>
              ))}
            </div>

            {/* Analytics mini row */}
            {analytics && (
              <div className="flex gap-2 pt-1 border-t border-gray-100">
                <div className="flex-1 text-center">
                  <div className="flex items-center justify-center gap-1">
                    <span className="text-sm font-bold text-blue-600">{A.unique_sessions ?? '—'}</span>
                    <TrendPill curr={A.unique_sessions} prev={0} />
                  </div>
                  <div className="text-xs text-gray-400">מבקרים</div>
                </div>
                <div className="flex-1 text-center border-x border-gray-100">
                  <div className="text-sm font-bold text-green-600">{A.whatsapp_clicks ?? '—'}</div>
                  <div className="text-xs text-gray-400">💬 WhatsApp</div>
                </div>
                <div className="flex-1 text-center">
                  <div className="text-sm font-bold text-purple-600">{A.total_clicks ?? '—'}</div>
                  <div className="text-xs text-gray-400">לחיצות</div>
                </div>
              </div>
            )}

            {/* Mini pie */}
            {sources.length > 0 && (
              <div dir="rtl">
                <p className="text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wide">מקורות תנועה</p>
                <div className="flex items-center gap-3">
                  <ResponsiveContainer width={70} height={70}>
                    <PieChart>
                      <Pie data={sources} dataKey="value" cx="50%" cy="50%"
                        innerRadius={20} outerRadius={34} paddingAngle={2}>
                        {sources.map((s, i) => <Cell key={i} fill={s.color} />)}
                      </Pie>
                      <Tooltip formatter={(v, n) => [v, n]} contentStyle={{ fontSize: 11, borderRadius: 8 }} />
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="flex flex-col gap-1 flex-1">
                    {sources.slice(0, 4).map((s, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-xs text-gray-600">
                        <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ background: s.color }} />
                        <span className="flex-1 truncate">{s.name}</span>
                        <span className="font-semibold">{s.value}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center" dir="rtl">
        <span className="text-xs text-gray-400">
          {site.addedAt ? `נוסף ${new Date(site.addedAt).toLocaleDateString('he-IL')}` : ''}
        </span>
        <span className="text-xs text-blue-500 font-medium">פתח ←</span>
      </div>
    </div>
  )
}
// ── Global stats bar ──────────────────────────────────────────────────────────
function GlobalStats({ sites, allSummaries, allAnalytics }) {
  const totals = Object.values(allSummaries).reduce((a, s) => {
    if (!s) return a
    a.leads     += Number(s.leads_period    ?? 0)
    a.customers += Number(s.customers_active ?? s.customers_total ?? 0)
    a.deals     += Number(s.deals_open_amount ?? 0)
    a.revenue   += Number(s.deals_won_period  ?? 0)
    return a
  }, { leads: 0, customers: 0, deals: 0, revenue: 0 })

  const totAn = Object.values(allAnalytics).reduce((a, an) => {
    if (!an) return a
    a.visitors  += Number(an.unique_sessions  ?? 0)
    a.whatsapp  += Number(an.whatsapp_clicks  ?? 0)
    a.clicks    += Number(an.total_clicks     ?? 0)
    return a
  }, { visitors: 0, whatsapp: 0, clicks: 0 })

  const online = sites.filter(s => s.status === 'online').length

  const stats = [
    { icon:'🌐', label:'אתרים מחוברים',    value:`${online} / ${sites.length}`,               color:'text-blue-600' },
    { icon:'👁️', label:'מבקרים החודש',      value: totAn.visitors.toLocaleString(),            color:'text-blue-600' },
    { icon:'💬', label:'לחיצות WhatsApp',   value: totAn.whatsapp.toLocaleString(),            color:'text-green-600' },
    { icon:'🎯', label:'לידים החודש',       value: totals.leads.toLocaleString(),              color:'text-indigo-600' },
    { icon:'👥', label:'סה״כ לקוחות',       value: totals.customers.toLocaleString(),          color:'text-purple-600' },
    { icon:'💰', label:'הכנסות שנסגרו',      value:`₪${totals.revenue.toLocaleString()}`,       color:'text-green-600' },
    { icon:'📊', label:'Pipeline פתוח',     value:`₪${totals.deals.toLocaleString()}`,         color:'text-amber-600' },
  ]

  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-8" dir="rtl">
      {stats.map((s, i) => (
        <div key={i} className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-right">
          <div className="text-xl mb-1">{s.icon}</div>
          <div className={`text-xl font-bold ${s.color}`}>{s.value}</div>
          <div className="text-xs text-gray-500 mt-0.5 leading-tight">{s.label}</div>
        </div>
      ))}
    </div>
  )
}

// ── Main dashboard ────────────────────────────────────────────────────────────
export default function MultiSiteDashboard({ sites, onSelectSite, onAddSite }) {
  const [summaries,  setSummaries]  = useState({})
  const [analytics,  setAnalytics]  = useState({})
  const [search, setSearch]         = useState('')
  const [sortBy, setSortBy]         = useState('name')

  useEffect(() => {
    const now    = new Date()
    const toD    = now.toISOString().slice(0, 10)
    const fromD  = new Date(+now - 30 * 86400000).toISOString().slice(0, 10)

    sites.forEach(async site => {
      try {
        const r = await axios.get(`/api/sites/${site.id}/proxy/reports/summary?from=${fromD}&to=${toD}`)
        setSummaries(prev => ({ ...prev, [site.id]: unwrap(r.data) }))
      } catch {}
      try {
        const r = await axios.get(`/api/sites/${site.id}/proxy/analytics/summary?date_from=${fromD}&date_to=${toD}`)
        setAnalytics(prev => ({ ...prev, [site.id]: unwrap(r.data) }))
      } catch {}
    })
  }, [sites])

  const onlineSites = sites.filter(s => s.status === 'online').length
  const errorSites  = sites.filter(s => s.status === 'error').length

  // Filter + sort
  const filtered = sites
    .filter(s => !search || s.name.toLowerCase().includes(search.toLowerCase()) || s.url.includes(search))
    .sort((a, b) => {
      if (sortBy === 'leads') {
        return (summaries[b.id]?.leads_period || 0) - (summaries[a.id]?.leads_period || 0)
      }
      if (sortBy === 'visitors') {
        return (analytics[b.id]?.unique_sessions || 0) - (analytics[a.id]?.unique_sessions || 0)
      }
      if (sortBy === 'status') {
        const order = { online: 0, error: 1, unknown: 2 }
        return (order[a.status] ?? 2) - (order[b.status] ?? 2)
      }
      return a.name.localeCompare(b.name, 'he')
    })

  return (
    <div className="min-h-screen bg-gray-50 p-6" dir="rtl">

      {/* ── Header ── */}
      <div className="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">דשבורד ראשי</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {onlineSites} אתרים פעילים
            {errorSites > 0 && <span className="text-red-400 mr-2"> · {errorSites} לא מגיבים</span>}
          </p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {/* Search */}
          <input
            value={search} onChange={e => setSearch(e.target.value)}
            placeholder="🔍 חפש אתר..."
            className="border border-gray-200 rounded-xl px-3 py-1.5 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 w-44"
          />
          {/* Sort */}
          <select value={sortBy} onChange={e => setSortBy(e.target.value)}
            className="border border-gray-200 rounded-xl px-3 py-1.5 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="name">מיון: שם</option>
            <option value="status">מיון: סטטוס</option>
            <option value="leads">מיון: לידים</option>
            <option value="visitors">מיון: מבקרים</option>
          </select>
          <button onClick={onAddSite}
            className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700 transition shadow-sm">
            + הוסף אתר
          </button>
        </div>
      </div>

      {/* ── Global totals ── */}
      <GlobalStats sites={sites} allSummaries={summaries} allAnalytics={analytics} />

      {/* ── Site cards ── */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        {filtered.map((site, idx) => (
          <SiteCard
            key={site.id}
            site={site}
            colorIdx={sites.indexOf(site)}
            onSelect={onSelectSite}
          />
        ))}

        {/* Add site placeholder */}
        <button onClick={onAddSite}
          className="border-2 border-dashed border-gray-200 rounded-2xl p-8 flex flex-col items-center justify-center gap-3 text-gray-400 hover:border-blue-300 hover:text-blue-400 transition-colors min-h-[280px]">
          <span className="text-4xl">+</span>
          <span className="text-sm font-medium">הוסף אתר חדש</span>
        </button>
      </div>

      {filtered.length === 0 && search && (
        <div className="text-center py-16 text-gray-400">
          <div className="text-4xl mb-3">🔍</div>
          <p className="text-sm">לא נמצאו אתרים עבור "{search}"</p>
        </div>
      )}
    </div>
  )
}