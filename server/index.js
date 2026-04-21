// ── Load environment variables from .env ──────────────────────────────────────
require('dotenv').config({ path: require('path').join(__dirname, '../.env') })

const express  = require('express')
const cors     = require('cors')
const path     = require('path')
const fs       = require('fs')
const axios    = require('axios')
const nodemailer = require('nodemailer')

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
    return { 'X-API-Key': site.apiKey }
  }
  return {}
}

// ── WordPress proxy request ───────────────────────────────────────────────────
async function wpRequest(site, method, wpPath, data = null) {
  const url = site.url.replace(/\/$/, '') + '/wp-json/ofnoacomps-crm/v1' + wpPath
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
      : { 'X-API-Key': apiKey }
    await axios.get(url.replace(/\/$/, '') + '/wp-json/ofnoacomps-crm/v1/reports/summary', { headers, timeout: 8000 })
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
app.get('/api/health', (req, res) => {
  const emailConfigured = !!process.env.EMAIL_PASS
  res.json({ ok: true, sites: loadSites().length, version: '1.1.0', emailConfigured })
})

// ── Ping all sites every 60s ──────────────────────────────────────────────────
async function pingAllSites() {
  const sites = loadSites(); let changed = false
  for (const site of sites) {
    const was = site.status
    try {
      await axios.get(site.url.replace(/\/$/,'') + '/wp-json/ofnoacomps-crm/v1/reports/summary', { headers: buildAuthHeaders(site), timeout: 5000 })
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

// ── Email (nodemailer) ────────────────────────────────────────────────────────
const EMAIL_USER = process.env.EMAIL_USER || 'ofnoacomps@gmail.com'
const EMAIL_PASS = process.env.EMAIL_PASS || ''

let transporter = null

function getTransporter() {
  if (!transporter && EMAIL_PASS) {
    transporter = nodemailer.createTransport({
      service: 'gmail',
      auth: { user: EMAIL_USER, pass: EMAIL_PASS }
    })
  }
  return transporter
}

// ── POST /api/elementor/leads ─────────────────────────────────────────────────
app.post('/api/elementor/leads', async (req, res) => {
  try {
    const leadData = req.body
    if (!leadData || Object.keys(leadData).length === 0) {
      return res.status(400).json({ error: 'No lead data provided' })
    }

    const mailer = getTransporter()

    if (!mailer) {
      console.warn('⚠️  EMAIL_PASS לא מוגדר — הליד נשמר אך מייל לא נשלח.')
      // שמור ליד בלי מייל
      return saveLeadFile(leadData, false, res)
    }

    const rows = Object.entries(leadData)
      .map(([k, v]) => `<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;background:#f9f9f9;font-weight:bold;width:130px;">${k}</td><td style="padding:8px 12px;border-bottom:1px solid #eee;">${v}</td></tr>`)
      .join('')

    const html = `<!DOCTYPE html><html dir="rtl" lang="he">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f0f0f0;margin:0;padding:20px;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <div style="background:#16a34a;padding:20px 24px;">
      <h2 style="color:#fff;margin:0;font-size:20px;">&#128640; ליד חדש מאתר TRYIT</h2>
      <p style="color:#bbf7d0;margin:4px 0 0;font-size:13px;">${new Date().toLocaleString('he-IL')}</p>
    </div>
    <table style="width:100%;border-collapse:collapse;">${rows}</table>
    <div style="padding:14px 24px;background:#f9f9f9;border-top:1px solid #eee;font-size:11px;color:#888;">
      נשלח אוטומטית על ידי Ofnoacomps CRM
    </div>
  </div>
</body></html>`

    await mailer.sendMail({
      from:    `"Ofnoacomps CRM" <${EMAIL_USER}>`,
      to:      EMAIL_USER,
      subject: 'ליד מכירה מאתר TRYIT',
      html,
      replyTo: leadData.email || leadData.Email || EMAIL_USER
    })

    console.log('📧 מייל נשלח בהצלחה על ליד חדש')
    return saveLeadFile(leadData, true, res)

  } catch (error) {
    console.error('❌ שגיאה בעיבוד ליד:', error.message)
    res.status(500).json({ error: 'Failed to process lead', details: error.message })
  }
})

function saveLeadFile(leadData, emailSent, res) {
  try {
    const leadsDir = path.join(__dirname, '../_leads')
    if (!fs.existsSync(leadsDir)) fs.mkdirSync(leadsDir, { recursive: true })
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-')
    const leadFile  = path.join(leadsDir, `lead_${timestamp}.json`)
    fs.writeFileSync(leadFile, JSON.stringify({ timestamp: new Date().toISOString(), data: leadData, emailSent }, null, 2))
    res.status(200).json({ status: 'success', message: emailSent ? 'Lead received and email sent' : 'Lead captured (no email)', leadId: timestamp })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
}

// ── GET /api/elementor/leads ──────────────────────────────────────────────────
app.get('/api/elementor/leads', (req, res) => {
  try {
    const leadsDir = path.join(__dirname, '../_leads')
    if (!fs.existsSync(leadsDir)) return res.json({ count: 0, leads: [] })
    const files = fs.readdirSync(leadsDir)
      .filter(f => f.startsWith('lead_') && f.endsWith('.json'))
      .sort().reverse()
    const leads = files.map(f => JSON.parse(fs.readFileSync(path.join(leadsDir, f), 'utf8')))
    res.json({ count: leads.length, leads })
  } catch (error) {
    res.status(500).json({ error: error.message })
  }
})

// ── Start ─────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`\n🚀 Ofnoacomps CRM Server v1.1 — http://localhost:${PORT}`)
  console.log(`   Sites file : ${SITES_FILE}`)
  console.log(`   Email user : ${EMAIL_USER}`)
  console.log(`   Email pass : ${EMAIL_PASS ? '✅ מוגדר' : '❌ חסר — הגדר EMAIL_PASS ב-.env'}`)
  console.log(`   Leads POST : POST /api/elementor/leads\n`)
})
