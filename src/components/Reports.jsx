import { useEffect, useState } from 'react'
import { BarChart, Bar, LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts'
import { useApi } from '../hooks/useApi'

const COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']
const SRC_HE = { direct:'ישיר', google_organic:'גוגל אורגני', google_ads:'גוגל ממומן',
  facebook_ads:'פייסבוק ממומן', facebook_organic:'פייסבוק', instagram:'אינסטגרם',
  referral:'הפניה', email:'אימייל', whatsapp:'וואטסאפ', other:'אחר' }

export default function Reports({ site }) {
  const { get, loading } = useApi(site.id)
  const cur = site.currency || '₪'

  const [from, setFrom] = useState(new Date().toISOString().slice(0,7) + '-01')
  const [to,   setTo]   = useState(new Date().toISOString().slice(0,10))

  const [summary,     setSummary]     = useState(null)
  const [leadsTime,   setLeadsTime]   = useState([])
  const [bySource,    setBySource]    = useState([])
  const [byStatus,    setByStatus]    = useState([])
  const [funnel,      setFunnel]      = useState([])
  const [revenue,     setRevenue]     = useState([])
  const [leaderboard, setLeaderboard] = useState([])

  const load = () => {
    const q = `from=${from}&to=${to}`
    get(`/reports/summary?${q}`).then(r => setSummary(r?.data)).catch(() => {})
    get(`/reports/leads-over-time?${q}`).then(r => setLeadsTime(r?.data||[])).catch(() => {})
    get(`/reports/leads-by-source?${q}`).then(r => setBySource(r?.data||[])).catch(() => {})
    get(`/reports/pipeline-funnel`).then(r => setFunnel(r?.data||[])).catch(() => {})
    get(`/reports/revenue?${q}&group_by=month`).then(r => setRevenue(r?.data||[])).catch(() => {})
    get(`/reports/leaderboard?${q}`).then(r => setLeaderboard(r?.data||[])).catch(() => {})
  }

  useEffect(() => { load() }, [site.id, from, to])

  const KPI = ({ label, value, icon, color = 'blue' }) => {
    const c = { blue:'text-blue-700 bg-blue-50 border-blue-200', green:'text-green-700 bg-green-50 border-green-200',
      amber:'text-amber-700 bg-amber-50 border-amber-200' }[color]
    return (
      <div className={`card border ${c} text-center`}>
        <div className="text-2xl mb-1">{icon}</div>
        <div className="text-xl font-bold">{value ?? '—'}</div>
        <div className="text-xs font-medium mt-1">{label}</div>
      </div>
    )
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header + date filter */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h2 className="text-xl font-bold text-slate-800">דוחות ואנליטיקה</h2>
        <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-4 py-2">
          <input type="date" value={from} onChange={e => setFrom(e.target.value)}
            className="text-sm border-0 outline-none bg-transparent" />
          <span className="text-slate-400 text-sm">—</span>
          <input type="date" value={to} onChange={e => setTo(e.target.value)}
            className="text-sm border-0 outline-none bg-transparent" />
          <button onClick={load} className="bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-blue-700 transition">עדכן</button>
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <KPI icon="👥" label="לידים בתקופה"    value={summary?.leads_period}   color="blue" />
        <KPI icon="🆕" label="לידים חדשים"     value={summary?.leads_new}      color="amber" />
        <KPI icon="🔄" label="שיעור המרה"      value={`${summary?.conversion_rate||0}%`} color="green" />
        <KPI icon="💰" label="הכנסות בתקופה"   value={`${cur}${Number(summary?.deals_won_period||0).toLocaleString()}`} color="green" />
        <KPI icon="📊" label="Pipeline פתוח"   value={`${cur}${Number(summary?.deals_open_amount||0).toLocaleString()}`} color="blue" />
        <KPI icon="🏢" label="סה״כ לקוחות"    value={summary?.customers_total} color="blue" />
      </div>

      {/* Charts row 1 */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="card lg:col-span-2">
          <h3 className="font-semibold text-slate-700 mb-4">לידים לפי יום</h3>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={leadsTime}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="period" tick={{fontSize:11}} />
              <YAxis tick={{fontSize:11}} />
              <Tooltip />
              <Bar dataKey="count" fill="#3b82f6" radius={[4,4,0,0]} name="לידים" />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="card">
          <h3 className="font-semibold text-slate-700 mb-4">לפי מקור תנועה</h3>
          {bySource.length ? (
            <>
              <ResponsiveContainer width="100%" height={140}>
                <PieChart>
                  <Pie data={bySource} dataKey="count" nameKey="source" cx="50%" cy="50%" outerRadius={60} paddingAngle={2}>
                    {bySource.map((_,i) => <Cell key={i} fill={COLORS[i%COLORS.length]}/>)}
                  </Pie>
                  <Tooltip formatter={(v,n) => [v, SRC_HE[n]||n]} />
                </PieChart>
              </ResponsiveContainer>
              <div className="space-y-1 mt-2">
                {bySource.map((r,i) => (
                  <div key={r.source} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full" style={{background: COLORS[i%COLORS.length]}}/>
                      <span className="text-slate-600">{SRC_HE[r.source]||r.source}</span>
                    </div>
                    <span className="font-semibold">{r.count}</span>
                  </div>
                ))}
              </div>
            </>
          ) : <div className="h-40 flex items-center justify-center text-slate-400 text-sm">אין נתונים</div>}
        </div>
      </div>

      {/* Charts row 2 */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="card">
          <h3 className="font-semibold text-slate-700 mb-4">הכנסות לפי חודש</h3>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={revenue}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="period" tick={{fontSize:11}}/>
              <YAxis tick={{fontSize:11}}/>
              <Tooltip formatter={v => [`${cur}${Number(v).toLocaleString()}`, 'הכנסות']} />
              <Bar dataKey="revenue" fill="#10b981" radius={[4,4,0,0]} name="הכנסות" />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="card">
          <h3 className="font-semibold text-slate-700 mb-4">פאנל מכירות — שלבים</h3>
          {funnel.length ? (
            <div className="space-y-2">
              {funnel.map(row => (
                <div key={row.id} className="flex items-center gap-3">
                  <span className="text-xs text-slate-500 w-28 truncate">{row.name}</span>
                  <div className="flex-1 h-6 bg-slate-100 rounded-lg overflow-hidden">
                    <div className="h-full rounded-lg flex items-center px-2 text-xs text-white font-semibold"
                      style={{ width: `${Math.max(8, (row.deal_count / (funnel[0]?.deal_count||1)) * 100)}%`, background: row.color }}>
                      {row.deal_count}
                    </div>
                  </div>
                  <span className="text-xs font-semibold text-slate-600 w-20 text-left">{cur}{Number(row.total_value||0).toLocaleString()}</span>
                </div>
              ))}
            </div>
          ) : <div className="h-40 flex items-center justify-center text-slate-400 text-sm">אין נתונים</div>}
        </div>
      </div>

      {/* Leaderboard */}
      {leaderboard.length > 0 && (
        <div className="card">
          <h3 className="font-semibold text-slate-700 mb-4">🏆 דירוג נציגים</h3>
          <table className="crm-table">
            <thead><tr><th>#</th><th>נציג</th><th>עסקאות שנסגרו</th><th>הכנסות</th></tr></thead>
            <tbody>
            {leaderboard.map((r,i) => (
              <tr key={r.owner_id}>
                <td className="text-lg">{['🥇','🥈','🥉'][i] || (i+1)}</td>
                <td className="font-medium text-slate-800">{r.user_name}</td>
                <td>{r.deals_won}</td>
                <td className="font-bold text-green-700">{cur}{Number(r.revenue||0).toLocaleString()}</td>
              </tr>
            ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
