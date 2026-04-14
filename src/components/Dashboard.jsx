import { useEffect, useState, useCallback } from 'react'
import { LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { useApi } from '../hooks/useApi'

const COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']
const SOURCES_HE = {
  direct:'ישיר', google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
  facebook_ads:'פייסבוק ממומן', facebook_organic:'פייסבוק אורגני',
  instagram:'אינסטגרם', referral:'הפניה', email:'אימייל',
  whatsapp:'וואטסאפ', bing_organic:'Bing', linkedin:'LinkedIn', waze:'Waze',
}

function trendPct(curr, prev) {
  const c = Number(curr || 0), p = Number(prev || 0)
  if (p === 0) return null
  return Math.round(((c - p) / p) * 100)
}

function TrendBadge({ curr, prev }) {
  const pct = trendPct(curr, prev)
  if (pct === null || isNaN(pct)) return null
  const up = pct >= 0
  return (
    <span className={`inline-flex items-center gap-0.5 text-xs font-semibold px-1.5 py-0.5 rounded-full ${
      up ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'
    }`}>
      {up ? '↑' : '↓'} {Math.abs(pct)}%
    </span>
  )
}

function KpiCard({ icon, label, value, curr, prev, color = 'blue' }) {
  const map = {
    blue:  'bg-blue-50   border-blue-100  text-blue-700',
    green: 'bg-green-50  border-green-100 text-green-700',
    amber: 'bg-amber-50  border-amber-100 text-amber-700',
    purple:'bg-purple-50 border-purple-100 text-purple-700',
  }
  return (
    <div className={`rounded-xl border p-4 flex flex-col gap-2 ${map[color] || map.blue}`}>
      <div className="flex items-start justify-between">
        <span className="text-xl">{icon}</span>
        {curr !== undefined && <TrendBadge curr={curr} prev={prev} />}
      </div>
      <div className="text-2xl font-bold leading-none">{value ?? '—'}</div>
      <div className="text-xs font-medium opacity-70">{label}</div>
    </div>
  )
}

function Funnel({ visitors, leads, customers, revenue, currency = '₪' }) {
  const steps = [
    { label:'מבקרים',  icon:'🌐', num: visitors,  display: visitors,  color:'#3b82f6', rate: null },
    { label:'לידים',   icon:'👤', num: leads,     display: leads,     color:'#8b5cf6',
      rate: visitors > 0 ? `${((leads / Math.max(visitors,1))*100).toFixed(1)}% מהמבקרים` : null },
    { label:'לקוחות', icon:'🏢', num: customers, display: customers, color:'#10b981',
      rate: leads > 0 ? `${((customers / Math.max(leads,1))*100).toFixed(1)}% מהלידים` : null },
    { label:'הכנסות', icon:'💰', num: Number(revenue||0),
      display: `${currency}${Number(revenue||0).toLocaleString()}`, color:'#f59e0b', rate: null },
  ]
  const maxNum = Math.max(...steps.map(s => s.num), 1)
  return (
    <div className="space-y-3">
      {steps.map((s, i) => {
        const barPct = Math.max(6, (s.num / maxNum) * 100)
        return (
          <div key={i} className="flex items-center gap-3">
            <span className="text-base w-5 text-center flex-shrink-0">{s.icon}</span>
            <span className="text-xs font-medium text-slate-600 w-14 text-right flex-shrink-0">{s.label}</span>
            <div className="flex-1 h-8 bg-slate-100 rounded-lg overflow-hidden">
              <div className="h-full rounded-lg flex items-center px-3 transition-all duration-700"
                style={{ width: `${barPct}%`, background: s.color }}>
                <span className="text-white text-xs font-bold truncate">{s.display}</span>
              </div>
            </div>
            {s.rate
              ? <span className="text-xs text-slate-400 w-36 text-right flex-shrink-0">{s.rate}</span>
              : <span className="w-36 flex-shrink-0" />
            }
          </div>
        )
      })}
    </div>
  )
}

function PeriodBtn({ d, days, onChange }) {
  const labels = { 7:'שבוע', 14:'14 ימים', 30:'חודש', 90:'3 חודשים' }
  return (
    <button onClick={() => onChange(d)}
      className={`px-3 py-1 text-xs font-medium rounded-full transition ${
        days === d ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
      }`}>
      {labels[d] || `${d}י׳`}
    </button>
  )
}
export default function Dashboard({ site }) {
  const { get } = useApi(site.id)
  const [days, setDays]           = useState(30)
  const [loading, setLoading]     = useState(true)
  const [summary, setSummary]     = useState(null)
  const [prevSum, setPrevSum]     = useState(null)
  const [analytics, setAnalytics] = useState(null)
  const [prevAn, setPrevAn]       = useState(null)
  const [leadsTime, setLT]        = useState([])
  const [bySource, setBS]         = useState([])

  const load = useCallback(async () => {
    setLoading(true)
    const now   = new Date()
    const toD   = now.toISOString().slice(0, 10)
    const fromD = new Date(+now - days * 86400000).toISOString().slice(0, 10)
    const pFrom = new Date(+now - days * 2 * 86400000).toISOString().slice(0, 10)

    const [s, p, an, pan, lt, bs] = await Promise.allSettled([
      get(`/reports/summary?from=${fromD}&to=${toD}`),
      get(`/reports/summary?from=${pFrom}&to=${fromD}`),
      get(`/analytics/summary?date_from=${fromD}&date_to=${toD}`),
      get(`/analytics/summary?date_from=${pFrom}&date_to=${fromD}`),
      get(`/reports/leads-over-time?from=${fromD}&to=${toD}`),
      get(`/reports/leads-by-source?from=${fromD}&to=${toD}`),
    ])
    const u = r => r.status === 'fulfilled' ? (r.value?.data ?? r.value) : null
    setSummary(u(s)); setPrevSum(u(p))
    setAnalytics(u(an)); setPrevAn(u(pan))
    setLT(u(lt) || []); setBS(u(bs) || [])
    setLoading(false)
  }, [site.id, days])

  useEffect(() => { load() }, [load])

  const cur = site.currency || '₪'
  const S = summary || {}, P = prevSum || {}, A = analytics || {}, PA = prevAn || {}

  const crmKpis = [
    { icon:'👥', label:`לידים (${days}י׳)`,    color:'blue',   value: S.leads_period,                       curr: S.leads_period,      prev: P.leads_period },
    { icon:'🆕', label:'לידים חדשים',           color:'amber',  value: S.leads_new,                          curr: S.leads_new,         prev: P.leads_new },
    { icon:'🏢', label:'לקוחות פעילים',          color:'blue',   value: S.customers_active,                   curr: S.customers_active,  prev: P.customers_active },
    { icon:'💰', label:'הכנסות שנסגרו',          color:'green',  value:`${cur}${Number(S.deals_won_period||0).toLocaleString()}`,   curr: S.deals_won_period,  prev: P.deals_won_period },
    { icon:'📊', label:'Pipeline פתוח',          color:'purple', value:`${cur}${Number(S.deals_open_amount||0).toLocaleString()}`,  curr: S.deals_open_amount, prev: P.deals_open_amount },
    { icon:'📈', label:'שיעור המרה',             color:'green',  value:`${S.conversion_rate||0}%`,            curr: S.conversion_rate,   prev: P.conversion_rate },
  ]

  const analyticsKpis = [
    { icon:'👁️',  label:'צפיות דפים',          color:'blue',   value: A.total_pageviews,  curr: A.total_pageviews,  prev: PA.total_pageviews },
    { icon:'👤',  label:'ביקורים ייחודיים',      color:'blue',   value: A.unique_sessions,  curr: A.unique_sessions,  prev: PA.unique_sessions },
    { icon:'💬',  label:'לחיצות WhatsApp',       color:'green',  value: A.whatsapp_clicks,  curr: A.whatsapp_clicks,  prev: PA.whatsapp_clicks },
    { icon:'🖱️', label:'סה״כ לחיצות',           color:'purple', value: A.total_clicks,     curr: A.total_clicks,     prev: PA.total_clicks },
  ]

  return (
    <div className="p-6 space-y-6" dir="rtl">

      {/* ── Header ── */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold text-slate-800">השגרה — {site.name}</h2>
          <p className="text-sm text-slate-400 mt-0.5">
            {loading ? '⏳ טוען...' : `נתוני ${days} הימים האחרונים`}
          </p>
        </div>
        <div className="flex items-center gap-1.5">
          {[7, 14, 30, 90].map(d => <PeriodBtn key={d} d={d} days={days} onChange={setDays} />)}
          <button onClick={load} title="רענן"
            className="mr-1 p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
            🔄
          </button>
        </div>
      </div>

      {/* ── CRM KPIs ── */}
      <div>
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">📋 CRM</p>
        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
          {crmKpis.map((k, i) => <KpiCard key={i} {...k} />)}
        </div>
      </div>

      {/* ── Analytics KPIs ── */}
      <div>
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">🌐 תנועה לאתר</p>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {analyticsKpis.map((k, i) => <KpiCard key={i} {...k} />)}
        </div>
      </div>

      {/* ── Charts ── */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="bg-white rounded-xl border border-slate-200 p-5 lg:col-span-2">
          <h3 className="text-sm font-semibold text-slate-700 mb-4">לידים לפי יום</h3>
          {leadsTime.length ? (
            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={leadsTime}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Line type="monotone" dataKey="count" stroke="#3b82f6" strokeWidth={2} dot={false} name="לידים" />
              </LineChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-[200px] flex items-center justify-center text-slate-400 text-sm">אין נתונים עדיין</div>
          )}
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-5">
          <h3 className="text-sm font-semibold text-slate-700 mb-4">לפי מקור</h3>
          {bySource.length ? (
            <>
              <ResponsiveContainer width="100%" height={150}>
                <PieChart>
                  <Pie data={bySource} dataKey="count" nameKey="source" cx="50%" cy="50%" outerRadius={65} paddingAngle={2}>
                    {bySource.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                  </Pie>
                  <Tooltip formatter={(v, n) => [v, SOURCES_HE[n] || n]} />
                </PieChart>
              </ResponsiveContainer>
              <div className="space-y-1.5 mt-3">
                {bySource.slice(0, 5).map((row, i) => (
                  <div key={row.source} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full" style={{ background: COLORS[i % COLORS.length] }} />
                      <span className="text-slate-600">{SOURCES_HE[row.source] || row.source}</span>
                    </div>
                    <span className="font-semibold text-slate-800">{row.count}</span>
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="h-[200px] flex items-center justify-center text-slate-400 text-sm">אין נתונים עדיין</div>
          )}
        </div>
      </div>

      {/* ── Funnel ── */}
      <div className="bg-white rounded-xl border border-slate-200 p-5">
        <h3 className="text-sm font-semibold text-slate-700 mb-5">🔽 פאנל המרה</h3>
        <Funnel
          visitors={A.unique_sessions || 0}
          leads={S.leads_period || 0}
          customers={S.customers_active || 0}
          revenue={S.deals_won_period || 0}
          currency={cur}
        />
      </div>

      {/* ── Recent Leads ── */}
      <RecentLeads site={site} />
    </div>
  )
}

function RecentLeads({ site }) {
  const { get } = useApi(site.id)
  const [leads, setLeads] = useState([])

  useEffect(() => {
    get('/leads?limit=8').then(r => setLeads(r?.data || [])).catch(() => {})
  }, [site.id])

  const STATUS_HE = { new:'חדש', contacted:'בטיפול', qualified:'מוסמך', converted:'הומר', lost:'אבוד' }
  const BADGE = {
    new:'bg-blue-100 text-blue-700', contacted:'bg-amber-100 text-amber-700',
    qualified:'bg-purple-100 text-purple-700', converted:'bg-green-100 text-green-700',
    lost:'bg-red-100 text-red-500',
  }

  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-sm font-semibold text-slate-700">לידים אחרונים</h3>
        <span className="text-xs text-slate-400">{leads.length} תוצאות</span>
      </div>
      {leads.length ? (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-slate-400 text-xs border-b border-slate-100">
                <th className="text-right pb-2 font-medium">שם</th>
                <th className="text-right pb-2 font-medium">אימייל / טלפון</th>
                <th className="text-right pb-2 font-medium">מקור</th>
                <th className="text-right pb-2 font-medium">סטטוס</th>
                <th className="text-right pb-2 font-medium">תאריך</th>
              </tr>
            </thead>
            <tbody>
              {leads.map(l => (
                <tr key={l.id} className="border-b border-slate-50 hover:bg-slate-50 transition">
                  <td className="py-2.5 font-medium text-slate-800">
                    {[l.first_name, l.last_name].filter(Boolean).join(' ') || '(ללא שם)'}
                  </td>
                  <td className="py-2.5">
                    <div className="text-slate-700">{l.email}</div>
                    <div className="text-slate-400 text-xs">{l.phone}</div>
                  </td>
                  <td className="py-2.5">
                    <span className="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs">
                      {SOURCES_HE[l.source] || l.source}
                    </span>
                  </td>
                  <td className="py-2.5">
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${BADGE[l.status] || BADGE.new}`}>
                      {STATUS_HE[l.status] || l.status}
                    </span>
                  </td>
                  <td className="py-2.5 text-slate-400 text-xs whitespace-nowrap">
                    {new Date(l.created_at).toLocaleDateString('he-IL')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="text-center text-slate-400 text-sm py-8">אין לידים עדיין</div>
      )}
    </div>
  )
}