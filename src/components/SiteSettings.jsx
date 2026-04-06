import { useState } from 'react'
import axios from 'axios'

export default function SiteSettings({ onSave, onCancel, initial = {} }) {
  const [form, setForm] = useState({
    name:        initial.name        || '',
    url:         initial.url         || '',
    authMode:    (initial.username ? 'apppass' : 'apikey'),
    apiKey:      initial.apiKey      || '',
    username:    initial.username    || '',
    appPassword: initial.appPassword || '',
    currency:    initial.currency    || '₪',
  })
  const [testing,  setTesting]  = useState(false)
  const [testMsg,  setTestMsg]  = useState(null)
  const [saving,   setSaving]   = useState(false)

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  const testConnection = async () => {
    setTesting(true)
    setTestMsg(null)
    try {
      const payload = {
        url: form.url,
        ...(form.authMode === 'apppass'
          ? { username: form.username, appPassword: form.appPassword }
          : { apiKey: form.apiKey }),
      }
      const { data } = await axios.post('/api/test-connection', payload)
      setTestMsg(data.ok
        ? { ok: true,  text: '✅ חיבור תקין! האתר מגיב.' }
        : { ok: false, text: `❌ ${data.error}` })
    } catch {
      setTestMsg({ ok: false, text: '❌ שגיאת חיבור' })
    } finally {
      setTesting(false)
    }
  }

  const handleSave = async () => {
    if (!form.name || !form.url) return alert('שם וכתובת אתר הם שדות חובה')
    if (form.authMode === 'apppass' && (!form.username || !form.appPassword))
      return alert('יש להזין שם משתמש וסיסמת אפליקציה')
    if (form.authMode === 'apikey' && !form.apiKey)
      return alert('יש להזין מפתח API')

    setSaving(true)
    try {
      const payload = {
        name:     form.name,
        url:      form.url.replace(/\/$/, ''),
        currency: form.currency,
        apiKey:      form.authMode === 'apikey'  ? form.apiKey      : null,
        username:    form.authMode === 'apppass' ? form.username    : null,
        appPassword: form.authMode === 'apppass' ? form.appPassword : null,
      }
      await onSave(payload)
    } finally {
      setSaving(false)
    }
  }

  const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-right'
  const labelCls = 'block text-sm font-medium text-gray-700 mb-1 text-right'

  return (
    <div className="space-y-4" dir="rtl">

      {/* Site name */}
      <div>
        <label className={labelCls}>שם האתר *</label>
        <input className={inputCls} placeholder="לדוגמה: hoco-israel"
          value={form.name} onChange={e => set('name', e.target.value)} />
      </div>

      {/* URL */}
      <div>
        <label className={labelCls}>כתובת האתר *</label>
        <input className={inputCls} placeholder="https://www.example.com" dir="ltr"
          value={form.url} onChange={e => set('url', e.target.value)} />
      </div>

      {/* Auth mode toggle */}
      <div>
        <label className={labelCls}>שיטת אימות</label>
        <div className="flex gap-2">
          <button
            onClick={() => set('authMode', 'apppass')}
            className={`flex-1 py-2 rounded-lg text-sm font-medium border transition-colors ${
              form.authMode === 'apppass'
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
            }`}>
            🔑 Application Password
          </button>
          <button
            onClick={() => set('authMode', 'apikey')}
            className={`flex-1 py-2 rounded-lg text-sm font-medium border transition-colors ${
              form.authMode === 'apikey'
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
            }`}>
            🗝️ API Key
          </button>
        </div>
      </div>

      {/* Application Password fields */}
      {form.authMode === 'apppass' && (
        <div className="space-y-3 bg-blue-50 rounded-lg p-4">
          <p className="text-xs text-blue-700 font-medium">
            WordPress Admin → משתמשים → הפרופיל שלי → Application Passwords
          </p>
          <div>
            <label className={labelCls}>שם משתמש WordPress *</label>
            <input className={inputCls} placeholder="admin" dir="ltr"
              value={form.username} onChange={e => set('username', e.target.value)} />
          </div>
          <div>
            <label className={labelCls}>Application Password *</label>
            <input className={inputCls} placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" dir="ltr"
              type="password"
              value={form.appPassword} onChange={e => set('appPassword', e.target.value)} />
          </div>
        </div>
      )}

      {/* API Key field */}
      {form.authMode === 'apikey' && (
        <div className="space-y-2 bg-gray-50 rounded-lg p-4">
          <div>
            <label className={labelCls}>מפתח API (מההגדרות של הפלאגין) *</label>
            <input className={inputCls} placeholder="hoco_..." dir="ltr"
              type="password"
              value={form.apiKey} onChange={e => set('apiKey', e.target.value)} />
          </div>
        </div>
      )}

      {/* Currency */}
      <div>
        <label className={labelCls}>מטבע</label>
        <select className={inputCls} value={form.currency} onChange={e => set('currency', e.target.value)}>
          <option value="₪">₪ שקל</option>
          <option value="$">$ דולר</option>
          <option value="€">€ יורו</option>
        </select>
      </div>

      {/* Test result */}
      {testMsg && (
        <div className={`p-3 rounded-lg text-sm text-right ${testMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
          {testMsg.text}
        </div>
      )}

      {/* Buttons */}
      <div className="flex gap-2 pt-2">
        <button
          onClick={handleSave}
          disabled={saving}
          className="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
          {saving ? 'שומר...' : '💾 שמור אתר'}
        </button>
        <button
          onClick={testConnection}
          disabled={testing}
          className="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 disabled:opacity-50">
          {testing ? 'בודק...' : '🔌 בדוק חיבור'}
        </button>
        {onCancel && (
          <button onClick={onCancel}
            className="px-4 bg-white border border-gray-300 text-gray-600 py-2 rounded-lg text-sm hover:bg-gray-50">
            ביטול
          </button>
        )}
      </div>
    </div>
  )
}
