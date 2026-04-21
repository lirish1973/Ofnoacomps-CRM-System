import { useEffect, useState, useCallback, useRef } from 'react'
import { AreaChart, Area, PieChart, Pie, BarChart, Bar, Cell,
         XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts'
import { useApi } from '../hooks/useApi'

// ── Constants ──────────────────────────────────────────────────────────────────
const PALETTE  = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']
const AUTO_SEC = 30

const SOURCES_HE = {
  direct:'ישיר', google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
  facebook_ads:'פייסבוק ממומן', facebook_organic:'פייסבוק אורגני',
  instagram:'אינסטגרם', referral:'הפניה', email:'אימייל',
  whatsapp:'וואטסאפ', bing_organic:'Bing', linkedin:'LinkedIn', waze:'Waze', youtube:'YouTube',
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function fmtNum(n) {
  const v = Number(n || 0)
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M'
  if (v >= 1_000)     return (v / 1_000).toFixed(1) + 'K'
  return v.toLocaleString('he-IL')
}

function trendPct(curr, prev) {
  const c = Number(curr || 0), p = Number(prev || 0)
  if (p === 0) return null
  return Math.round(((c - p) / p) * 100)
}

// ── Micro-components ───────────────────────────────────────────────────────────

function LiveBadge() {
  return (
    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
      LIVE
    </span>
  )
}

function TrendBadge({ curr, prev }) {
  const pct = trendPct(curr, prev)
  if (pct === null || isNaN(pct)) return null
  const up = pct >= 0
  return (
    <span className={`text-xs font-bold px-1.5 py-0.5 rounded-full ${up ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
      {up ? '↑' : '↓'} {Math.abs(pct)}%
    </span>
  )
}

function PeriodBtn({ d, active, onChange }) {
  const lbl = { 7:'7י', 14:'14י', 30:'30י', 90:'90י' }
  return (
    <button onClick={() => onChange(d)}
      className={`px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${
        active ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-500 border border-slate-200 hover:border-indigo-300 hover:text-indigo-600'
      }`}>
      {lbl[d]}
    </button>
  )
}

function KpiCard({ icon, label, value, curr, prev, color = 'indigo' }) {
  const border = { indigo:'border-indigo-100', green:'border-emerald-100', amber:'border-amber-100',
                   violet:'border-violet-100', red:'border-red-100',    teal:'border-teal-100',
                   sky:'border-sky-100',       rose:'border-rose-100' }
  const iconBg  = { indigo:'bg-indigo-50 text-indigo-600', green:'bg-emerald-50 text-emerald-600',
                    amber:'bg-amber-50 text-amber-600',    violet:'bg-violet-50 text-violet-600',
                    red:'bg-red-50 text-red-500',          teal:'bg-teal-50 text-teal-600',
                    sky:'bg-sky-50 text-sky-600',          rose:'bg-rose-50 text-rose-500' }
  return (
    <div className={`bg-white rounded-2xl border-2 ${border[color]||border.indigo} p-4 flex flex-col gap-2.5 shadow-sm hover:shadow-md transition-shadow duration-200`}>
      <div className="flex items-start justify-between">
        <span className={`w-9 h-9 rounded-xl flex items-center justify-center text-lg flex-shrink-0 ${iconBg[color]||iconBg.indigo}`}>{icon}</span>
        <TrendBadge curr={curr} prev={prev} />
      </div>
      <div>
        <div className="text-2xl font-black text-slate-900 leading-none tabular-nums">{value ?? '—'}</div>
        <div className="text-xs text-slate-500 mt-1 font-medium">{label}</div>
      </div>
    </div>
  )
}

function SectionLabel({ icon, text }) {
  return (
    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
      <span>{icon}</span>{text}
    </p>
  )
}

function Card({ title, subtitle, children }) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      {title && (
        <div className="px-5 pt-4 pb-3 border-b border-slate-100">
          <h3 className="text-sm font-bold text-slate-800">{title}</h3>
          {subtitle && <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>}
        </div>
      )}
      <div className="p-5">{children}</div>
    </div>
  )
}

function CartFunnelStep({ icon, label, count, rate, grad, isLast }) {
  return (
    <div className="flex items-center gap-1.5 flex-1">
      <div className={`flex-1 rounded-xl p-3 bg-gradient-to-br ${grad} text-white text-center shadow-sm`}>
        <div className="text-2xl mb-1">{icon}</div>
        <div className="text-xl font-black leading-none tabular-nums">{fmtNum(count)}</div>
        <div className="text-xs mt-1 font-semibold opacity-90">{label}</div>
        {rate != null && <div className="text-xs mt-0.5 opacity-75 font-medium">{rate}% המרה</div>}
      </div>
      {!isLast && <div className="text-slate-400 text-lg font-light flex-shrink-0">→</div>}
    </div>
  )
}

function SourceRow({ name, sessions, pct, color }) {
  return (
    <div className="flex items-center gap-2">
      <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ background: color }} />
      <span className="text-xs text-slate-600 w-28 truncate">{SOURCES_HE[name] || name}</span>
      <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
        <div className="h-full rounded-full transition-all duration-700" style={{ width: `${pct}%`, background: color }} />
      </div>
      <span className="text-xs font-bold text-slate-700 w-8 text-left tabular-nums">{fmtNum(sessions)}</span>
      <span className="text-xs text-slate-400 w-9 text-left">{pct}%</span>
    </div>
  )
}

function StatPill({ label, value, good }) {
  return (
    <div className={`rounded-xl p-3 text-center border ${good === true ? 'bg-emerald-50 border-emerald-100' : good === false ? 'bg-red-50 border-red-100' : 'bg-slate-50 border-slate-100'}`}>
      <div className={`text-lg font-black ${good === true ? 'text-emerald-700' : good === false ? 'text-red-600' : 'text-slate-800'}`}>{value}</div>
      <div className="text-xs text-slate-500 mt-0.5 font-medium">{label}</div>
    </div>
  )
}

// ── CRM Conversion Funnel ──────────────────────────────────────────────────────
function CrmFunnel({ visitors, leads, customers, revenue, currency }) {
  const steps = [
    { label:'מבקרים',  icon:'🌐', num: visitors,  display: fmtNum(visitors),  color:'#6366f1', rate: null },
    { label:'לידים',   icon:'🎯', num: leads,     display: fmtNum(leads),     color:'#8b5cf6',
      rate: visitors > 0 ? `${((leads/Math.max(visitors,1))*100).toFixed(1)}% מהמבקרים` : null },
    { label:'לקוחות',  icon:'🏢', num: customers, display: fmtNum(customers), color:'#10b981',
      rate: leads > 0 ? `${((customers/Math.max(leads,1))*100).toFixed(1)}% מהלידים` : null },
    { label:'הכנסות',  icon:'💰', num: Number(revenue||0),
      display: `${currency}${fmtNum(revenue||0)}`, color:'#f59e0b', rate: null },
  ]
  const maxNum = Math.max(...steps.map(s=>s.num), 1)
  return (
    <div className="space-y-2.5">
      {steps.map((s,i) => {
        const barPct = Math.max(6, (s.num/maxNum)*100)
        return (
          <div key={i} className="flex items-center gap-3">
            <span className="text-base w-5 text-center flex-shrink-0">{s.icon}</span>
            <span className="text-xs font-semibold text-slate-600 w-14 text-right flex-shrink-0">{s.label}</span>
            <div className="flex-1 h-7 bg-slate-100 rounded-lg overflow-hidden">
              <div className="h-full rounded-lg flex items-center px-3 transition-all duration-700"
                style={{ width:`${barPct}%`, background:s.color }}>
                <span className="text-white text-xs font-black truncate">{s.display}</span>
              </div>
            </div>
            {s.rate
              ? <span className="text-xs text-slate-400 w-36 text-right flex-shrink-0">{s.rate}</span>
              : <span className="w-36 flex-shrink-0" />}
          </div>
        )
      })}
    </div>
  )
}

// ── Main Dashboard Component ───────────────────────────────────────────────────
export default function Dashboard({ site }) {
  const { get } = useApi(site.id)

  const [days, setDays]           = useState(30)
  const [loading, setLoading]     = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [summary,  setSummary]    = useState(null)
  const [prevSum,  setPrevSum]    = useState(null)
  const [analytics,setAnalytics]  = useState(null)
  const [prevAn,   setPrevAn]     = useState(null)
  const [leadsTime,setLT]         = useState([])
  const [bySource, setBS]         = useState([])
  const [ecom,     setEcom]       = useState(null)
  const [lastUpdate,setLastUpdate]= useState(null)
  const [countdown,setCountdown]  = useState(AUTO_SEC)

  const timerRef  = useRef(null)
  const countRef  = useRef(null)
  const daysRef   = useRef(days)
  useEffect(() => { daysRef.current = days }, [days])

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true)
    else setRefreshing(true)

    const now   = new Date()
    const toD   = now.toISOString().slice(0,10)
    const fromD = new Date(+now - days*86400000).toISOString().slice(0,10)
    const pFrom = new Date(+now - days*2*86400000).toISOString().slice(0,10)

    const [s,p,an,pan,lt,bs,ec] = await Promise.allSettled([
      get(`/reports/summary?from=${fromD}&to=${toD}`),
      get(`/reports/summary?from=${pFrom}&to=${fromD}`),
      get(`/analytics/summary?date_from=${fromD}&date_to=${toD}`),
      get(`/analytics/summary?date_from=${pFrom}&date_to=${fromD}`),
      get(`/reports/leads-over-time?from=${fromD}&to=${toD}`),
      get(`/analytics/sources?days=${days}`),
      get(`/analytics/ecommerce?days=${days}`),
    ])
    const u = r => r.status==='fulfilled' ? (r.value?.data ?? r.value) : null

    setSummary(u(s));  setPrevSum(u(p))
    setAnalytics(u(an)); setPrevAn(u(pan))
    setLT(u(lt) || [])
    const srcRaw = u(bs)
    setBS(srcRaw?.sources || srcRaw || [])
    setEcom(u(ec))
    setLastUpdate(new Date())
    setCountdown(AUTO_SEC)
    if (!silent) setLoading(false)
    else setRefreshing(false)
  }, [site.id, days])

  useEffect(() => { load() }, [load])

  // Auto-refresh every 30s
  useEffect(() => {
    timerRef.current = setInterval(() => load(true), AUTO_SEC * 1000)
    return () => clearInterval(timerRef.current)
  }, [load])

  // Countdown
  useEffect(() => {
    countRef.current = setInterval(() => setCountdown(c => (c > 1 ? c - 1 : AUTO_SEC)), 1000)
    return () => clearInterval(countRef.current)
  }, [])

  const cur  = site.currency || '₪'
  const S    = summary   || {}
  const P    = prevSum   || {}
  const A    = analytics || {}
  const PA   = prevAn    || {}
  const E    = ecom      || {}

  const hasEcom = E.cart_adds > 0 || E.checkout_starts > 0 || E.completions > 0

  const totalSrc = bySource.reduce((a,x) => a + parseInt(x.sessions||x.count||0), 0)
  const sourcesTop = bySource.slice(0,7).map(s => ({
    ...s,
    pct: totalSrc > 0 ? Math.round((parseInt(s.sessions||s.count||0)/totalSrc)*100) : 0
  }))

  const funnelSteps = [
    { icon:'👥', label:'מבקרים',        count: A.unique_sessions||0, rate: null,
      grad:'from-indigo-500 to-indigo-700' },
    { icon:'🛒', label:'הוספות לעגלה',  count: E.cart_adds||0,
      rate: (A.unique_sessions||0)>0 ? +((E.cart_adds||0)/(A.unique_sessions||1)*100).toFixed(1) : null,
      grad:'from-violet-500 to-violet-700' },
    { icon:'💳', label:'התחלת תשלום',   count: E.checkout_starts||0,
      rate: (E.cart_adds||0)>0 ? +((E.checkout_starts||0)/(E.cart_adds||1)*100).toFixed(1) : null,
      grad:'from-amber-500 to-amber-700' },
    { icon:'✅', label:'רכישות',         count: E.completions||0,
      rate: (E.checkout_starts||0)>0 ? +((E.completions||0)/(E.checkout_starts||1)*100).toFixed(1) : null,
      grad:'from-emerald-500 to-emerald-700' },
  ]

  const secsAgo = lastUpdate ? Math.round((new Date()-lastUpdate)/1000) : null

  return (
    <div className="flex-1 overflow-auto bg-slate-50" dir="rtl">
      <div className="p-6 max-w-screen-2xl mx-auto space-y-6">

        {/* ── Page Header ── */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white text-lg shadow-sm">📊</div>
            <div>
              <h2 className="text-lg font-black text-slate-900 leading-none">{site.name}</h2>
              <p className="text-xs text-slate-400 mt-0.5 flex items-center gap-2">
                {secsAgo !== null && `עודכן לפני ${secsAgo}ש׳`}
                {refreshing && <span className="text-indigo-500 animate-pulse font-medium">↻ מרענן...</span>}
              </p>
            </div>
            <LiveBadge />
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <div className="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1">
              {[7,14,30,90].map(d => <PeriodBtn key={d} d={d} active={days===d} onChange={setDays} />)}
            </div>
            <div className="flex items-center gap-1.5 text-xs text-slate-400 bg-white border border-slate-200 rounded-xl px-3 py-2">
              🔄 <span className="tabular-nums font-bold text-slate-500">{countdown}</span>
            </div>
            <button onClick={() => load(false)}
              className="px-4 py-2 text-xs font-bold bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition shadow-sm">
              רענן עכשיו
            </button>
          </div>
        </div>

        {/* ── Loading ── */}
        {loading && (
          <div className="flex items-center justify-center py-24">
            <div className="text-center space-y-4">
              <div className="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mx-auto" />
              <p className="text-sm text-slate-400 font-medium">טוען נתונים...</p>
            </div>
          </div>
        )}

        {!loading && (<>

          {/* ── KPIs: Traffic ── */}
          <div>
            <SectionLabel icon="🌐" text="תנועה לאתר" />
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <KpiCard icon="👁️" label="צפיות דפים"        value={fmtNum(A.total_pageviews)} curr={A.total_pageviews} prev={PA.total_pageviews} color="indigo" />
              <KpiCard icon="👤" label="ביקורים ייחודיים"  value={fmtNum(A.unique_sessions)} curr={A.unique_sessions} prev={PA.unique_sessions} color="sky"    />
              <KpiCard icon="💬" label="לחיצות WhatsApp"   value={fmtNum(A.whatsapp_clicks)} curr={A.whatsapp_clicks} prev={PA.whatsapp_clicks} color="green"  />
              <KpiCard icon="🖱️" label="סה״כ לחיצות"       value={fmtNum(A.total_clicks)}    curr={A.total_clicks}    prev={PA.total_clicks}    color="violet" />
            </div>
          </div>

          {/* ── KPIs: CRM ── */}
          <div>
            <SectionLabel icon="📋" text="CRM — מכירות" />
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <KpiCard icon="🎯" label={`לידים (${days}י׳)`}   value={fmtNum(S.leads_period)}             curr={S.leads_period}        prev={P.leads_period}        color="indigo" />
              <KpiCard icon="🏢" label="לקוחות פעילים"          value={fmtNum(S.customers_active)}         curr={S.customers_active}    prev={P.customers_active}    color="teal"   />
              <KpiCard icon="💰" label="הכנסות שנסגרו"          value={`${cur}${fmtNum(S.deals_won_period||0)}`}  curr={S.deals_won_period}    prev={P.deals_won_period}    color="green"  />
              <KpiCard icon="📊" label="Pipeline פתוח"          value={`${cur}${fmtNum(S.deals_open_amount||0)}`} curr={S.deals_open_amount}   prev={P.deals_open_amount}   color="amber"  />
            </div>
          </div>

          {/* ── KPIs: WooCommerce (shown when data exists) ── */}
          {(E.cart_adds > 0 || E.checkout_starts > 0) && (
            <div>
              <SectionLabel icon="🛒" text="WooCommerce — עגלת קניות" />
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <KpiCard icon="🛒" label="הוספות לעגלה"    value={fmtNum(E.cart_adds)}       color="violet" />
                <KpiCard icon="💳" label="התחלות תשלום"    value={fmtNum(E.checkout_starts)} color="amber"  />
                <KpiCard icon="✅" label="רכישות הושלמו"   value={fmtNum(E.completions)}     color="green"  />
                <KpiCard icon="🚪" label="נטישות עגלה"     value={fmtNum(E.abandonments)}    color="red"    />
              </div>
            </div>
          )}

          {/* ── Charts Row ── */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {/* Area Chart — Leads over time */}
            <Card title="📈 לידים לאורך זמן" subtitle={`${days} הימים האחרונים`}>
              <div className="lg:col-span-2">
                {leadsTime.length > 0 ? (
                  <ResponsiveContainer width="100%" height={220}>
                    <AreaChart data={leadsTime} margin={{ top:4, right:4, bottom:0, left:-20 }}>
                      <defs>
                        <linearGradient id="gradLeads" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%"  stopColor="#6366f1" stopOpacity={0.18} />
                          <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                      <XAxis dataKey="period" tick={{ fontSize:10, fill:'#94a3b8' }} />
                      <YAxis tick={{ fontSize:10, fill:'#94a3b8' }} />
                      <Tooltip contentStyle={{ fontSize:12, borderRadius:10, border:'1px solid #e2e8f0' }} />
                      <Area type="monotone" dataKey="count" stroke="#6366f1" strokeWidth={2.5}
                        fill="url(#gradLeads)" name="לידים" dot={false} activeDot={{ r:4 }} />
                    </AreaChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="h-[220px] flex flex-col items-center justify-center text-slate-400">
                    <span className="text-3xl mb-2">📭</span>
                    <p className="text-sm">אין נתונים עדיין</p>
                  </div>
                )}
              </div>
            </Card>

            {/* Traffic Sources */}
            <Card title="🌐 מקורות תנועה">
              {sourcesTop.length > 0 ? (
                <div className="space-y-3">
                  {sourcesTop.map((s,i) => (
                    <SourceRow key={s.source||i}
                      name={s.source} sessions={parseInt(s.sessions||s.count||0)}
                      pct={s.pct} color={PALETTE[i%PALETTE.length]} />
                  ))}
                </div>
              ) : (
                <div className="h-[200px] flex flex-col items-center justify-center text-slate-400">
                  <span className="text-3xl mb-2">📡</span>
                  <p className="text-sm">אין נתוני מקור עדיין</p>
                </div>
              )}
            </Card>
          </div>

          {/* ── WooCommerce Cart Funnel ── */}
          {(hasEcom || E.cart_adds !== undefined) && (
            <div>
              <div className="flex items-center justify-between mb-3">
                <SectionLabel icon="🛒" text="פאנל עגלת קניות — WooCommerce" />
                {E.abandonment_rate !== undefined && (
                  <span className={`text-xs font-bold px-3 py-1 rounded-full ${
                    E.abandonment_rate > 70 ? 'bg-red-100 text-red-700 border border-red-200' :
                    E.abandonment_rate > 40 ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                    'bg-emerald-100 text-emerald-700 border border-emerald-200'
                  }`}>
                    🚪 נטישת עגלה: {E.abandonment_rate}%
                  </span>
                )}
              </div>
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div className="flex items-stretch gap-2">
                  {funnelSteps.map((step,i) => (
                    <CartFunnelStep key={i} {...step} isLast={i===funnelSteps.length-1} />
                  ))}
                </div>
                {hasEcom && (
                  <div className="grid grid-cols-3 gap-3 mt-4">
                    <StatPill label="שיעור מעבר לתשלום"   value={`${E.checkout_rate||0}%`}    good={E.checkout_rate > 30} />
                    <StatPill label="שיעור השלמת רכישה"   value={`${E.completion_rate||0}%`}   good={E.completion_rate > 60} />
                    <StatPill label="שיעור נטישת עגלה"    value={`${E.abandonment_rate||0}%`}  good={E.abandonment_rate < 40} />
                  </div>
                )}
                {!hasEcom && (
                  <div className="mt-4 p-3 bg-slate-50 rounded-xl text-center text-sm text-slate-400">
                    📦 נתוני עגלה יופיעו לאחר שמבקרים יתחילו לקנות באתר
                  </div>
                )}
              </div>
            </div>
          )}

          {/* ── CRM Conversion Funnel ── */}
          <Card title="🔽 פאנל המרה — CRM" subtitle="מבקרים → לידים → לקוחות → הכנסות">
            <CrmFunnel
              visitors={A.unique_sessions || 0}
              leads={S.leads_period || 0}
              customers={S.customers_active || 0}
              revenue={S.deals_won_period || 0}
              currency={cur}
            />
          </Card>

          {/* ── Recent Leads ── */}
          <RecentLeads site={site} />

        </>)}
      </div>
    </div>
  )
}

// ── Recent Leads Table ─────────────────────────────────────────────────────────
function RecentLeads({ site }) {
  const { get } = useApi(site.id)
  const [leads, setLeads] = useState([])

  useEffect(() => {
    get('/leads?limit=10').then(r => setLeads(r?.data || [])).catch(() => {})
  }, [site.id])

  const STATUS_HE = { new:'חדש', contacted:'בטיפול', qualified:'מוסמך', converted:'הומר', lost:'אבוד' }
  const BADGE = {
    new:      'bg-indigo-50 text-indigo-700 border border-indigo-200',
    contacted:'bg-amber-50 text-amber-700 border border-amber-200',
    qualified:'bg-violet-50 text-violet-700 border border-violet-200',
    converted:'bg-emerald-50 text-emerald-700 border border-emerald-200',
    lost:     'bg-red-50 text-red-500 border border-red-200',
  }

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50">
        <div className="flex items-center gap-2">
          <span className="text-base">🎯</span>
          <h3 className="text-sm font-bold text-slate-800">לידים אחרונים</h3>
        </div>
        <span className="text-xs text-slate-400 bg-white border border-slate-200 px-2 py-0.5 rounded-full font-medium">{leads.length} תוצאות</span>
      </div>
      {leads.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-slate-400 text-xs bg-white border-b border-slate-100">
                <th className="text-right px-5 py-3 font-semibold">שם</th>
                <th className="text-right px-5 py-3 font-semibold">פרטי קשר</th>
                <th className="text-right px-5 py-3 font-semibold">מקור</th>
                <th className="text-right px-5 py-3 font-semibold">סטטוס</th>
                <th className="text-right px-5 py-3 font-semibold">תאריך</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {leads.map(l => (
                <tr key={l.id} className="hover:bg-indigo-50/40 transition-colors group">
                  <td className="px-5 py-3 font-bold text-slate-800">
                    {[l.first_name,l.last_name].filter(Boolean).join(' ') || '(ללא שם)'}
                  </td>
                  <td className="px-5 py-3">
                    <div className="text-slate-700 text-xs font-medium">{l.email}</div>
                    <div className="text-slate-400 text-xs">{l.phone}</div>
                  </td>
                  <td className="px-5 py-3">
                    <span className="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-medium">
                      {SOURCES_HE[l.source] || l.source || '—'}
                    </span>
                  </td>
                  <td className="px-5 py-3">
                    <span className={`px-2 py-0.5 rounded-lg text-xs font-bold ${BADGE[l.status]||BADGE.new}`}>
                      {STATUS_HE[l.status]||l.status}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">
                    {new Date(l.created_at).toLocaleDateString('he-IL')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="text-center text-slate-400 py-14">
          <div className="text-4xl mb-3">📭</div>
          <p className="text-sm font-medium">אין לידים עדיין</p>
          <p className="text-xs mt-1">לידים מהאתר יופיעו כאן בזמן אמת</p>
        </div>
      )}
    </div>
  )
}
