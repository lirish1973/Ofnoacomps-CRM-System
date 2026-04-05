import { useState } from 'react'

export default function SiteSettings({ mode, site, onSave, onClose }) {
  const [form, setForm] = useState({
    name:     site?.name     || '',
    url:      site?.url      || '',
    apiKey:   site?.apiKey   || '',
    currency: site?.currency || '₪',
  })
  const [testing, setTesting] = useState(false)
  const [testResult, setTest] = useState(null)

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const testConnection = async () => {
    setTesting(true)
    setTest(null)
    try {
      const res = await fetch('/api/test-connection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: form.url, apiKey: form.apiKey }),
      })
      const data = await res.json()
      setTest(data.ok ? { ok: true, msg: 'חיבור תקין ✅' } : { ok: false, msg: `שגיאה: ${data.error}` })
    } catch {
      setTest({ ok: false, msg: 'לא ניתן להתחבר' })
    } finally {
      setTesting(false)
    }
  }

  const handleSave = () => {
    if (!form.name || !form.url || !form.apiKey) return alert('נא למלא שם, כתובת URL ו-API Key')
    onSave(form)
  }

  const isModal = mode === 'add'

  const Content = () => (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-slate-600 mb-1.5">שם האתר</label>
        <input className="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400"
          placeholder="לדוגמה: hoco-israel.co.il" value={form.name} onChange={e => set('name', e.target.value)} />
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-600 mb-1.5">כתובת האתר (URL)</label>
        <input className="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400"
          placeholder="https://www.hoco-israel.co.il" value={form.url} onChange={e => set('url', e.target.value)} />
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-600 mb-1.5">
          API Key
          <span className="text-slate-400 font-normal mr-2">— מהגדרות הפלאגין ב-WordPress</span>
        </label>
        <input className="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-400"
          placeholder="hoco_apikey_xxxxxxxxxxxxxxxx" value={form.apiKey} onChange={e => set('apiKey', e.target.value)} />
        <p className="text-xs text-slate-400 mt-1.5">
          WordPress Admin → Hoco CRM → הגדרות → צור API Key
        </p>
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-600 mb-1.5">סמל מטבע</label>
        <input className="w-28 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400"
          placeholder="₪" value={form.currency} onChange={e => set('currency', e.target.value)} />
      </div>

      {/* Test connection */}
      <div className="flex items-center gap-3">
        <button onClick={testConnection} disabled={testing || !form.url || !form.apiKey}
          className="bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 px-4 py-2 rounded-xl text-sm font-medium transition">
          {testing ? 'בודק...' : '🔌 בדוק חיבור'}
        </button>
        {testResult && (
          <span className={`text-sm font-medium ${testResult.ok ? 'text-green-600' : 'text-red-600'}`}>
            {testResult.msg}
          </span>
        )}
      </div>

      <div className="flex gap-3 justify-end pt-2">
        {isModal && <button onClick={onClose} className="px-5 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50 transition">ביטול</button>}
        <button onClick={handleSave} className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition">
          {mode === 'add' ? '+ הוסף אתר' : 'שמור שינויים'}
        </button>
      </div>
    </div>
  )

  if (isModal) return (
    <div className="fixed inset-0 z-50 flex items-center justify-center" dir="rtl">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 z-10">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-bold text-slate-800">הוסף אתר חדש</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-2xl leading-none">✕</button>
        </div>
        <Content />
      </div>
    </div>
  )

  return (
    <div className="p-6 max-w-lg">
      <h2 className="text-xl font-bold text-slate-800 mb-6">הגדרות — {site?.name}</h2>
      <div className="card">
        <Content />
      </div>

      {/* API docs */}
      <div className="card mt-4">
        <h3 className="font-semibold text-slate-700 mb-3">📡 API Endpoint</h3>
        <div className="bg-slate-50 rounded-lg px-4 py-3 font-mono text-xs text-slate-600 break-all">
          {form.url ? `${form.url}/wp-json/hoco-crm/v1/` : 'https://your-site.com/wp-json/hoco-crm/v1/'}
        </div>
      </div>
    </div>
  )
}
