import { useEffect, useState } from 'react'
import { LineChart, Line, BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { useApi } from '../hooks/useApi'

const COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']

const SOURCES_HE = {
  direct: 'ישיר', google_organic: 'גוגל אורגני', google_ads: 'גוגל ממומן',
  facebook_ads: 'פייסבוק ממומן', facebook_organic: 'פייסבוק אורגני',
  instagram: 'אינסטגרם', referral: 'הפניה', email: 'אימייל', whatsapp: 'וואטסאפ', other: 'אחר',
}

function StatCard({ label, value, sub, color = 'blue', icon }) {
  const colors = {
    blue:  'bg-blue-50 text-blue-700 border-blue-200',
    green: 'bg-green-50 text-green-700 border-green-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
    red:   'bg-red-50 text-red-700 border-red-200',
  }
  return (
    <div className={`card border ${colors[color]} flex flex-col gap-1`}>
      <div className="text-lg">{icon}</div>
      <div className="text-2xl font-bold">{value ?? '—'}</div>
      <div className="text-sm font-medium">{label}</div>
      {sub && <div className="text-xs opacity-70">{sub}</div>}
    </div>
  )
}

export default function Dashboard({ site }) {
  const { get, loading } = useApi(site.id)
  const [summary, setSummary]   = useState(null)
  const [leadsTime, setLT]      = useState([])
  const [bySource, setBS]       = useState([])

  const today   = new Date().toISOString().slice(0,10)
  const month1  = today.slice(0,7) + '-01'

  useEffect(() => {
    get(`/reports/summary?from=${month1}&to=${today}`).then(r => setSummary(r?.data)).catch(() => {})
    get(`/reports/leads-over-time?from=${month1}&to=${today}`).then(r => setLT(r?.data || [])).catch(() => {})
    get(`/reports/leads-by-source?from=${month1}&to=${today}`).then(r => setBS(r?.data || [])).catch(() => {})
  }, [site.id])

  const cur = site.currency || '₪'

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold text-slate-800">דשבורד — {new Date().toLocaleDateString('he-IL',{month:'long',year:'numeric'})}</h2>
        {loading && <span className="text-xs text-slate-400 animate-pulse">טוען...</span>}
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <StatCard icon="👥" label="לידים בחודש"      value={summary?.leads_period}   color="blue" />
        <StatCard icon="🆕" label="לידים חדשים"      value={summary?.leads_new}      color="amber" />
        <StatCard icon="🏢" label="לקוחות פעילים"    value={summary?.customers_active} color="blue" />
        <StatCard icon="💰" label="הכנסות החודש"     value={`${cur}${Number(summary?.deals_won_period||0).toLocaleString()}`} color="green" />
        <StatCard icon="📊" label="Pipeline פתוח"    value={`${cur}${Number(summary?.deals_open_amount||0).toLocaleString()}`} color="blue" />
        <StatCard icon="🔄" label="שיעור המרה"       value={`${summary?.conversion_rate||0}%`} color="green" />
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Leads over time */}
        <div className="card lg:col-span-2">
          <h3 className="font-semibold text-slate-700 mb-4">לידים לפי יום — החודש</h3>
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

        {/* By source */}
        <div className="card">
          <h3 className="font-semibold text-slate-700 mb-4">לפי מקור</h3>
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
              <div className="space-y-1 mt-2">
                {bySource.slice(0,5).map((row, i) => (
                  <div key={row.source} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full" style={{background: COLORS[i % COLORS.length]}}/>
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

      {/* Recent leads */}
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
  const BADGE     = { new:'badge-new', contacted:'badge-contacted', qualified:'badge-qualified', converted:'badge-converted', lost:'badge-lost' }
  const SRC_HE    = SOURCES_HE

  return (
    <div className="card">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-slate-700">לידים אחרונים</h3>
        <span className="text-xs text-slate-400">{leads.length} תוצאות</span>
      </div>
      {leads.length ? (
        <div className="overflow-x-auto">
          <table className="crm-table">
            <thead><tr>
              <th>שם</th><th>אימייל / טלפון</th><th>מקור</th><th>סטטוס</th><th>תאריך</th>
            </tr></thead>
            <tbody>
            {leads.map(l => (
              <tr key={l.id}>
                <td className="font-medium text-slate-800">{[l.first_name, l.last_name].filter(Boolean).join(' ') || '(ללא שם)'}</td>
                <td>
                  <div className="text-slate-700">{l.email}</div>
                  <div className="text-slate-400 text-xs">{l.phone}</div>
                </td>
                <td><span className={`source-chip source-${l.source}`}>{SRC_HE[l.source] || l.source}</span></td>
                <td><span className={`badge ${BADGE[l.status] || 'badge-new'}`}>{STATUS_HE[l.status] || l.status}</span></td>
                <td className="text-slate-400 text-xs whitespace-nowrap">{new Date(l.created_at).toLocaleDateString('he-IL')}</td>
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
