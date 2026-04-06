const express = require('express')
const cors    = require('cors')
const path    = require('path')
const fs      = require('fs')
const axios   = require('axios')

const app  = express()
const PORT = 3001

app.use(cors())
app.use(express.json())

// ── Sites config ──────────────────────────────────────────────────────────────
const SITES_FILE = path.join(__dirname, 'sites.json')

function loadSites()       { if (!fs.existsSync(SITES_FILE)) return []; try { return JSON.parse(fs.readFileSync(SITES_FILE, 'utf8')) } catch { return [] } }
function saveSites(sites)  { fs.writeFileSync(SITES_FILE, JSON.stringify(sites, null, 2)) }
function findSite(id)      { return loadSites().find(s => s.id === id) }
function generateId()      { return Math.random().toString(36).slice(2,10) + Date.now().toString(36) }
function stripHtml(str)    { return typeof str === 'string' ? str.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().slice(0,300) : String(str||'') }

// ── Auth headers (API Key or WordPress Application Password) ──────────────────
function buildAuthHeaders(site) {
  if (site && site.username && site.appPassword) {
    return { Authorization: 'Basic ' + Buffer.from(`${site.username}:${site.appPassword}`).toString('base64') }
  }
  if (site && site.apiKey) {
    return { 'X-HOCO-API-Key': site.apiKey }
  }
  return {}
}

// ── WordPress proxy request ───────────────────────────────────────────────────
async function wpRequest(site, method, wpPath, data = null) {
  const url = site.url.replace(/\/$/, '') + '/wp-json/hoco-crm/v1' + wpPath
  const cfg = { method, url, headers: { 'Content-Type': 'application/json', ...buildAuthHeaders(site) }, timeout: 15000 }
  if (data) cfg.data = data
  return (await axios(cfg)).data
}

// ── Sites CRUD ────────────────────────────────────────────────────────────────
app.get('/api/sites', (req, res) => res.json(loadSites()))

app.post('/api/sites', (req, res) => {
  const { name, url, apiKey, username, appPassword, currency } = req.body
  if (!name || !url) return res.status(400).json({ error: 'name and url required' })
  if (!apiKey && !(username && appPassword)) return res.status(400).json({ error: 'Provide apiKey or username+appPassword' })
  const sites = loadSites()
  const site  = { id: generateId(), name, url: url.replace(/\/$/, ''), apiKey: apiKey||null, username: username||null, appPassword: appPassword||null, currency: currency||'₪', status: 'unknown', addedAt: new Date().toISOString() }
  sites.push(site)
  saveSites(sites)
  res.status(201).json(site)
})

app.patch('/api/sites/:id', (req, res) => {
  const sites = loadSites()
  const idx   = sites.findIndex(s => s.id === req.params.id)
  if (idx === -1) return res.status(404).json({ error: 'Not found' })
  sites[idx] = { ...sites[idx], ...req.body }
  saveSites(sites)
  res.json(sites[idx])
})

app.delete('/api/sites/:id', (req, res) => {
  saveSites(loadSites().filter(s => s.id !== req.params.id))
  res.json({ deleted: true })
})

// ── Test connection ───────────────────────────────────────────────────────────
app.post('/api/test-connection', async (req, res) => {
  const { url, apiKey, username, appPassword } = req.body
  try {
    const headers = username && appPassword
      ? { Authorization: 'Basic ' + Buffer.from(`${username}:${appPassword}`).toString('base64') }
      : { 'X-HOCO-API-Key': apiKey }
    await axios.get(url.replace(/\/$/, '') + '/wp-json/hoco-crm/v1/reports/summary', { headers, timeout: 8000 })
    res.json({ ok: true })
  } catch (e) {
    const st  = e.response?.status
    const msg = st === 401 ? 'שם משתמש או Application Password שגויים' :
                st === 403 ? 'אין הרשאות מספיקות' :
                st === 404 ? 'פלאגין לא מותקן באתר' :
                e.code === 'ECONNREFUSED' ? 'לא ניתן להתחבר לאתר' :
                e.code === 'ENOTFOUND'    ? 'כתובת האתר לא נמצאה' :
                stripHtml(e.response?.data?.message || e.message)
    res.json({ ok: false, error: msg })
  }
})

// ── Proxy ─────────────────────────────────────────────────────────────────────
app.all('/api/sites/:siteId/proxy/*', async (req, res) => {
  const site = findSite(req.params.siteId)
  if (!site) return res.status(404).json({ error: 'Site not found' })
  const wpPath = '/' + req.params[0] + (Object.keys(req.query).length ? '?' + new URLSearchParams(req.query) : '')
  try {
    res.json(await wpRequest(site, req.method, wpPath, ['POST','PATCH','PUT'].includes(req.method) ? req.body : null))
  } catch (e) {
    const status = e.response?.status || 500
    const raw    = e.response?.data?.message || e.response?.data?.error || e.message || 'שגיאת שרת'
    const msg    = status === 401 ? 'שם משתמש או Application Password שגויים' :
                   status === 403 ? 'אין הרשאות — נדרש Application Password עם הרשאת עריכה' :
                   status === 404 ? 'פלאגין לא מותקן באתר' :
                   stripHtml(raw)
    res.status(status).json({ error: msg })
  }
})

// ── Health ────────────────────────────────────────────────────────────────────
app.get('/api/health', (req, res) => res.json({ ok: true, sites: loadSites().length, version: '1.1.0' }))

// ── Ping all sites every 60s ──────────────────────────────────────────────────
async function pingAllSites() {
  const sites = loadSites(); let changed = false
  for (const site of sites) {
    const was = site.status
    try {
      await axios.get(site.url.replace(/\/$/,'') + '/wp-json/hoco-crm/v1/reports/summary', { headers: buildAuthHeaders(site), timeout: 5000 })
      site.status = 'online'
    } catch {
      site.status = 'error'
    }
    if (site.status !== was) changed = true
  }
  if (changed) saveSites(sites)
}
setInterval(pingAllSites, 60_000)
pingAllSites()

app.listen(PORT, () => {
  console.log(`\u{1F680} Ofnoacomps CRM Server v1.1 running on http://localhost:${PORT}`)
  console.log(`   Sites: ${SITES_FILE}`)
})
