import { useState, useEffect, useCallback } from 'react'
import {
  LineChart, Line, BarChart, Bar,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, Cell
} from 'recharts'

const COLORS = ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899']

const SOURCE_LABELS = {
  google_ads:'Google Ads', facebook_ads:'Facebook Ads', facebook_organic:'Facebook',
  instagram:'Instagram', google_organic:'Google Organic', direct:'ישיר',
  email:'אימייל', whatsapp:'WhatsApp', referral:'הפניה',
  bing_organic:'Bing', linkedin:'LinkedIn', youtube:'YouTube', twitter:'Twitter/X', waze:'Waze',
}
const EVENT_LABELS = {
  whatsapp_click:'לחיצות WhatsApp', phone_click:'לחיצות טלפון',
  button_click:'לחיצות כפתור', email_click:'לחיצות אימייל',
}
const DEVICE_LABELS = { mobile:'מובייל', desktop:'מחשב', tablet:'טאבלט' }

function KpiCard({ icon, label, value, sub, color='blue' }) {
  const map = {
    blue:'bg-blue-50 text-blue-700 border-blue-200',
    green:'bg-green-50 text-green-700 border-green-200',
    yellow:'bg-yellow-50 text-yellow-700 border-yellow-200',
    purple:'bg-purple-50 text-purple-700 border-purple-200',
  }
  return (
    <div className={`rounded-xl border p-4 flex items-start gap-3 ${map[color]||map.blue}`}>
      <span className="text-2xl">{icon}</span>
      <div>
        <p className="text-xs font-medium opacity-70">{label}</p>
        <p className="text-2xl font-bold">{(value??'—').toLocaleString?.()??value??'—'}</p>
        {sub && <p className="text-xs mt-0.5 opacity-60">{sub}</p>}
      </div>
    </div>
  )
}
function Section({ title, children }) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <h3 className="text-sm font-semibold text-slate-700 mb-4">{title}</h3>
      {children}
    </div>
  )
}
function Empty({ msg='אין נתונים לתצוגה' }) {
  return <div className="flex flex-col items-center py-10 text-slate-400"><span className="text-3xl mb-2">📭</span><p className="text-sm">{msg}</p></div>
}

export default function Analytics({ site }) {
  const [days,setDays]       = useState(30)
  const [summary,setSummary] = useState(null)
  const [pvData,setPvData]   = useState([])
  const [evData,setEvData]   = useState(null)
  const [srcData,setSrcData] = useState(null)
  const [loading,setLoading] = useState(true)
  const [error,setError]     = useState(null)

  const base = `/api/sites/${site.id}/proxy/wp-json/ofnoacomps-crm/v1`
  const go   = useCallback(async path => {
    const r = await fetch(`${base}${path}`,{headers:{'X-API-Key':site.apiKey||''}})
    if(!r.ok) throw new Error(`HTTP ${r.status}`)
    const j = await r.json(); return j.data??j
  },[base,site.apiKey])

  const load = useCallback(async () => {
    setLoading(true); setError(null)
    try {
      const [s,pv,ev,src] = await Promise.all([
        go(`/analytics/summary?days=${days}`),
        go(`/analytics/pageviews?days=${days}`),
        go(`/analytics/events?days=${days}`),
        go(`/analytics/sources?days=${days}`),
      ])
      setSummary(s); setPvData(pv); setEvData(ev); setSrcData(src)
    } catch(e){ setError(e.message) }
    finally{ setLoading(false) }
  },[go,days])

  useEffect(()=>{ load() },[load])
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold text-slate-800">📊 אנליטיקס</h2>
        <div className="flex items-center gap-2">
          {[7,14,30,90].map(d=>(
            <button key={d} onClick={()=>setDays(d)}
              className={`px-3 py-1 text-xs rounded-full font-medium transition ${days===d?'bg-blue-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}>
              {d} ימים
            </button>
          ))}
          <button onClick={load} className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100" title="רענן">🔄</button>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm">
          ❌ שגיאה: {error}<br/><span className="text-xs opacity-70">ודא שהפלאגין המעודכן מותקן על אתר זה.</span>
        </div>
      )}

      {loading && <div className="flex justify-center py-16 text-3xl animate-spin">⏳</div>}

      {!loading && summary && (<>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <KpiCard icon="👁️"  label="צפיות דפים"           value={summary.total_pageviews} color="blue"   />
          <KpiCard icon="👤"  label="ביקורים ייחודיים"       value={summary.unique_sessions} color="green"  />
          <KpiCard icon="🖱️"  label="לחיצות סה״כ"           value={summary.total_clicks}    color="purple" />
          <KpiCard icon="💬"  label="לחיצות WhatsApp"       value={summary.whatsapp_clicks} color="green"
            sub={summary.unique_sessions>0?`${((summary.whatsapp_clicks/summary.unique_sessions)*100).toFixed(1)}% מהביקורים`:null} />
          <KpiCard icon="📞"  label="לחיצות טלפון"           value={summary.phone_clicks}    color="yellow" />
          <KpiCard icon="🔘"  label="לחיצות כפתור"           value={summary.button_clicks}   color="blue"   />
        </div>

        {summary.devices?.length>0 && (
          <div className="flex gap-2 flex-wrap">
            {summary.devices.map(d=>(
              <span key={d.device_type} className="px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                {d.device_type==='mobile'?'📱':d.device_type==='tablet'?'📲':'🖥️'}{' '}
                {DEVICE_LABELS[d.device_type]??d.device_type}: <strong>{parseInt(d.count).toLocaleString()}</strong>
              </span>
            ))}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <Section title="📈 צפיות לאורך זמן">
            {pvData.length>0?(
              <ResponsiveContainer width="100%" height={220}>
                <LineChart data={pvData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9"/>
                  <XAxis dataKey="period" tick={{fontSize:11}}/>
                  <YAxis tick={{fontSize:11}}/>
                  <Tooltip/>
                  <Legend/>
                  <Line type="monotone" dataKey="pageviews" name="צפיות" stroke="#3b82f6" dot={false} strokeWidth={2}/>
                  <Line type="monotone" dataKey="unique_sessions" name="ביקורים ייחודיים" stroke="#22c55e" dot={false} strokeWidth={2}/>
                </LineChart>
              </ResponsiveContainer>
            ):<Empty/>}
          </Section>

          <Section title="🌐 מקורות תנועה">
            {srcData?.sources?.length>0?(
              <div className="space-y-2">
                {srcData.sources.slice(0,8).map((s,i)=>{
                  const total=srcData.sources.reduce((a,x)=>a+parseInt(x.sessions),0)
                  const pct=total>0?((parseInt(s.sessions)/total)*100).toFixed(1):0
                  return(
                    <div key={s.source} className="flex items-center gap-2">
                      <div className="w-2 h-2 rounded-full flex-shrink-0" style={{background:COLORS[i%COLORS.length]}}/>
                      <span className="text-xs text-slate-600 w-32 truncate">{SOURCE_LABELS[s.source]??s.source}</span>
                      <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div className="h-full rounded-full" style={{width:`${pct}%`,background:COLORS[i%COLORS.length]}}/>
                      </div>
                      <span className="text-xs font-medium w-10 text-left">{parseInt(s.sessions).toLocaleString()}</span>
                      <span className="text-xs text-slate-400 w-10 text-left">{pct}%</span>
                    </div>
                  )
                })}
              </div>
            ):<Empty msg="אין נתוני מקור עדיין"/>}
          </Section>

          <Section title="🖱️ פעולות משתמשים">
            {evData?.by_type?.length>0?(
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={evData.by_type} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9"/>
                  <XAxis type="number" tick={{fontSize:11}}/>
                  <YAxis type="category" dataKey="event_type" width={130}
                    tickFormatter={t=>EVENT_LABELS[t]??t} tick={{fontSize:11}}/>
                  <Tooltip formatter={(v)=>[v,'לחיצות']}/>
                  <Bar dataKey="count" radius={[0,4,4,0]}>
                    {evData.by_type.map((_,i)=><Cell key={i} fill={COLORS[i%COLORS.length]}/>)}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            ):<Empty msg="אין נתוני אירועים עדיין"/>}
          </Section>

          <Section title="🔝 לחיצות מובילות">
            {evData?.top_labels?.length>0?(
              <div className="overflow-auto max-h-52">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-slate-500 border-b border-slate-100">
                      <th className="text-right pb-2 font-medium">סוג</th>
                      <th className="text-right pb-2 font-medium">תווית</th>
                      <th className="text-left pb-2 font-medium">כמות</th>
                    </tr>
                  </thead>
                  <tbody>
                    {evData.top_labels.map((r,i)=>(
                      <tr key={i} className="border-b border-slate-50 hover:bg-slate-50">
                        <td className="py-1.5">
                          <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${
                            r.event_type==='whatsapp_click'?'bg-green-100 text-green-700':
                            r.event_type==='phone_click'?'bg-yellow-100 text-yellow-700':'bg-blue-100 text-blue-700'
                          }`}>
                            {r.event_type==='whatsapp_click'?'💬':r.event_type==='phone_click'?'📞':'🔘'}{' '}
                            {EVENT_LABELS[r.event_type]??r.event_type}
                          </span>
                        </td>
                        <td className="py-1.5 max-w-xs truncate text-slate-700">{r.event_label||'—'}</td>
                        <td className="py-1.5 text-left font-semibold">{parseInt(r.count).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ):<Empty msg="אין נתוני לחיצות עדיין"/>}
          </Section>
        </div>

        {srcData?.campaigns?.length>0 && (
          <Section title="🎯 קמפיינים פעילים">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {srcData.campaigns.slice(0,9).map((c,i)=>(
                <div key={i} className="flex items-center gap-2 p-3 rounded-lg bg-slate-50 border border-slate-100">
                  <span className="text-lg">{c.source==='google_ads'?'🔵':c.source==='facebook_ads'?'📘':c.source==='instagram'?'📸':'🎯'}</span>
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-medium text-slate-800 truncate">{c.campaign}</p>
                    <p className="text-xs text-slate-500">{SOURCE_LABELS[c.source]??c.source}</p>
                  </div>
                  <span className="text-sm font-bold text-blue-600">{parseInt(c.sessions).toLocaleString()}</span>
                </div>
              ))}
            </div>
          </Section>
        )}

        {summary.top_pages?.length>0 && (
          <Section title="📄 דפים מובילים">
            <div className="space-y-1.5">
              {summary.top_pages.map((p,i)=>{
                const max=parseInt(summary.top_pages[0]?.views??1)
                const pct=((parseInt(p.views)/max)*100).toFixed(0)
                let label=p.page_url; try{label=new URL(p.page_url).pathname||'/'}catch(e){}
                return(
                  <div key={i} className="flex items-center gap-2">
                    <span className="text-xs text-slate-400 w-4 text-right">{i+1}.</span>
                    <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div className="h-full bg-blue-400 rounded-full" style={{width:`${pct}%`}}/>
                    </div>
                    <span className="text-xs text-slate-600 flex-1 min-w-0 truncate" title={p.page_url}>{label}</span>
                    <span className="text-xs font-semibold w-10 text-left">{parseInt(p.views).toLocaleString()}</span>
                  </div>
                )
              })}
            </div>
          </Section>
        )}
      </>)}

      {!loading && !error && !summary && (
        <div className="text-center py-16 text-slate-400">
          <div className="text-4xl mb-3">📭</div>
          <p className="text-sm">עדיין אין נתוני אנליטיקס מאתר זה.</p>
          <p className="text-xs mt-1">ודא שהפלאגין המעודכן מותקן ופעיל.</p>
        </div>
      )}
    </div>
  )
}