import { useState, useEffect, useCallback } from 'react'
import axios from 'axios'
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from 'recharts'

// ── Helpers ───────────────────────────────────────────────────────────────────
const AVATAR_COLORS = [
  '#3b82f6','#10b981','#f59e0b','#ef4444',
  '#8b5cf6','#06b6d4','#f97316','#84cc16',
]
const SOURCE_COLORS = {
  google_organic: '#34a853', google_ads: '#fbbc05',
  facebook_ads:   '#1877f2', instagram:  '#e1306c',
  direct:         '#6b7280', referral:   '#8b5cf6', other: '#94a3b8',
}
const SOURCE_LABELS = {
  google_organic: 'גוגל אורגני', google_ads: 'גוגל ממומן',
  facebook_ads:   'פייסבוק',     instagram:  'אינסטגרם',
  direct:         'ישיר',        referral:   'הפניה',     other: 'אחר',
}

function stripHtml(str) {
  if (typeof str !== 'string') return String(str || '')
  return str.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200)
}

function initials(name = '') {
  return (name.split(/\s+/).map(w => w[0]).join('').slice(0, 2) || '?').toUpperCase()
}

// Unwrap plugin's {data:{...}} envelope
function unwrap(res) {
  if (!res) return null
  return res.data ?? res
}

// ── Single site card ──────────────────────────────────────────────────────────
function SiteCard({ site, colorIdx, onSelect }) {
  const [summary, setSummary] = useState(null)
  const [sources, setSources] = useState([])
  const [loading, setLoading] = useState(true)
  const [error,   setError]   = useState(null)

  const color = AVATAR_COLORS[colorIdx % AVATAR_COLORS.length]

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)

    // 1. Fetch summary (required)
    try {
      const r = await axios.get(`/api/sites/${site.id}/proxy/reports/summary`)
      setSummary(unwrap(r.data))
    } catch (e) {
      const raw = e.response?.data?.error || e.message || 'שגיאה בטעינת נתונים'
      setError(stripHtml(raw))
      setLoading(false)
      return
    }

    // 2. Fetch sources (optional – don't crash card if it fails)
    try {
      const r = await axios.get(`/api/sites/${site.id}/proxy/reports/leads-by-source`)
      const rows = unwrap(r.data)
      if (Array.isArray(rows)) {
        setSources(rows.map(row => ({
          name:  SOURCE_LABELS[row.source] || row.source,
          value: Number(row.count ?? row.total ?? 0),
          color: SOURCE_COLORS[row.source] || '#94a3b8',
        })).filter(r => r.value > 0))
      }
    } catch {
      // sources are optional — silently ignore
    }

    setLoading(false)
  }, [site.id])

  useEffect(() => { fetchData() }, [fetchData])

  // Map actual API field names → display values
  const kpis = summary ? [
    {
      label: 'לידים החודש',
      value: summary.leads_period ?? summary.leads_30d ?? 0,
      icon: '🎯', color: '#3b82f6',
    },
    {
      label: 'סה"כ לקוחות',
      value: summary.customers_total ?? summary.total_customers ?? 0,
      icon: '👥', color: '#10b981',
    },
    {
      label: 'עסקאות פתוחות',
      value: `${site.currency || '₪'}${Number(summary.deals_open_amount ?? summary.open_deals ?? 0).toLocaleString()}`,
      icon: '🤝', color: '#f59e0b',
    },
    {
      label: 'הכנסות שנסגרו',
      value: `${site.currency || '₪'}${Number(summary.deals_won_period ?? summary.revenue_30d ?? 0).toLocaleString()}`,
      icon: '💰', color: '#8b5cf6',
    },
  ] : []

  return (
    <div
      onClick={() => onSelect(site)}
      className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow cursor-pointer flex flex-col"
    >
      {/* Header */}
      <div className="flex items-center gap-3 p-5 border-b border-gray-50" dir="rtl">
        <div
          className="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
          style={{ background: color }}
        >
          {initials(site.name)}
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-gray-900 text-base truncate">{site.name}</h3>
          <p className="text-xs text-gray-400 truncate">{site.url}</p>
        </div>
        <span className={`flex-shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium ${
          site.status === 'online' ? 'bg-green-50 text-green-600' :
          site.status === 'error'  ? 'bg-red-50 text-red-500'    :
                                     'bg-gray-100 text-gray-400'
        }`}>
          {site.status === 'online' ? '● מחובר' :
           site.status === 'error'  ? '● לא מגיב' : '● בודק...'}
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
            <span className="text-3xl">🔐</span>
            <p className="text-sm text-amber-600 font-medium">נדרשת הגדרת חיבור</p>
            <p className="text-xs text-gray-400 max-w-[200px]">{error}</p>
          </div>
        )}

        {!loading && !error && summary && (
          <div className="space-y-4">
            {/* KPIs 2×2 */}
            <div className="grid grid-cols-2 gap-3" dir="rtl">
              {kpis.map((k, i) => (
                <div key={i} className="bg-gray-50 rounded-xl p-3 text-right">
                  <div className="text-lg font-bold" style={{ color: k.color }}>{k.value}</div>
                  <div className="text-xs text-gray-500 mt-0.5 leading-tight">{k.label}</div>
                </div>
              ))}
            </div>

            {/* Sources mini pie (optional) */}
            {sources.length > 0 && (
              <div dir="rtl">
                <p className="text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wide">מקורות תנועה</p>
                <div className="flex items-center gap-3">
                  <ResponsiveContainer width={80} height={80}>
                    <PieChart>
                      <Pie data={sources} dataKey="value" cx="50%" cy="50%"
                           innerRadius={22} outerRadius={38} paddingAngle={2}>
                        {sources.map((s, i) => <Cell key={i} fill={s.color} />)}
                      </Pie>
                      <Tooltip
                        formatter={(v, n) => [v, n]}
                        contentStyle={{ fontSize: 11, borderRadius: 8 }}
                      />
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
function GlobalStats({ sites, allSummaries }) {
  const totals = Object.values(allSummaries).reduce((acc, s) => {
    if (!s) return acc
    acc.leads     += Number(s.leads_period    ?? s.leads_30d         ?? 0)
    acc.customers += Number(s.customers_total ?? s.total_customers   ?? 0)
    acc.deals     += Number(s.deals_open_amount ?? s.open_deals      ?? 0)
    acc.revenue   += Number(s.deals_won_period  ?? s.revenue_30d     ?? 0)
    return acc
  }, { leads: 0, customers: 0, deals: 0, revenue: 0 })

  const online = sites.filter(s => s.status === 'online').length

  const stats = [
    { icon: '🌐', label: 'אתרים מחוברים', value: `${online} / ${sites.length}`,            sub: '' },
    { icon: '🎯', label: 'לידים החודש',   value: totals.leads,                              sub: 'כלל האתרים' },
    { icon: '👥', label: 'סה"כ לקוחות',  value: totals.customers,                          sub: 'כלל האתרים' },
    { icon: '💰', label: 'ערך עסקאות',    value: `₪${totals.deals.toLocaleString()}`,       sub: 'פתוחות' },
    { icon: '🏆', label: 'הכנסות שנסגרו', value: `₪${totals.revenue.toLocaleString()}`,     sub: 'החודש' },
  ]

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8" dir="rtl">
      {stats.map((s, i) => (
        <div key={i} className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-right">
          <div className="text-2xl mb-1">{s.icon}</div>
          <div className="text-xl font-bold text-gray-900">{s.value}</div>
          <div className="text-xs font-medium text-gray-700 mt-0.5">{s.label}</div>
          {s.sub && <div className="text-xs text-gray-400">{s.sub}</div>}
        </div>
      ))}
    </div>
  )
}

// ── Main dashboard ────────────────────────────────────────────────────────────
export default function MultiSiteDashboard({ sites, onSelectSite, onAddSite }) {
  const [summaries, setSummaries] = useState({})

  useEffect(() => {
    sites.forEach(async (site) => {
      try {
        const r = await axios.get(`/api/sites/${site.id}/proxy/reports/summary`)
        setSummaries(prev => ({ ...prev, [site.id]: unwrap(r.data) }))
      } catch {}
    })
  }, [sites])

  const onlineSites = sites.filter(s => s.status === 'online').length
  const errorSites  = sites.filter(s => s.status === 'error').length

  return (
    <div className="min-h-screen bg-gray-50 p-6" dir="rtl">

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">דשבורד ראשי</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {onlineSites} אתרים פעילים
            {errorSites > 0 && <span className="text-red-400 mr-2"> · {errorSites} לא מגיבים</span>}
          </p>
        </div>
        <button
          onClick={onAddSite}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm"
        >
          + הוסף אתר
        </button>
      </div>

      {/* Global totals */}
      <GlobalStats sites={sites} allSummaries={summaries} />

      {/* Site cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        {sites.map((site, idx) => (
          <SiteCard
            key={site.id}
            site={site}
            colorIdx={idx}
            onSelect={onSelectSite}
          />
        ))}

        {/* Add site placeholder */}
        <button
          onClick={onAddSite}
          className="border-2 border-dashed border-gray-200 rounded-2xl p-8 flex flex-col items-center justify-center gap-3 text-gray-400 hover:border-blue-300 hover:text-blue-400 transition-colors min-h-[280px]"
        >
          <span className="text-4xl">+</span>
          <span className="text-sm font-medium">הוסף אתר חדש</span>
        </button>
      </div>
    </div>
  )
}
