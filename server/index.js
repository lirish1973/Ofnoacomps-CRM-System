const express  = require('express')
const cors     = require('cors')
const path     = require('path')
const fs       = require('fs')
const axios    = require('axios')
const { v4: uuid } = require('crypto')

const app  = express()
const PORT = 3001

// ── Middleware ────────────────────────────────────────────────────────────────
app.use(cors())
app.use(express.json())

// ── Sites config store ────────────────────────────────────────────────────────
const SITES_FILE = path.join(__dirname, 'sites.json')

function loadSites() {
  if (!fs.existsSync(SITES_FILE)) return []
  try { return JSON.parse(fs.readFileSync(SITES_FILE, 'utf8')) } catch { return [] }
}

function saveSites(sites) {
  fs.writeFileSync(SITES_FILE, JSON.stringify(sites, null, 2))
}

function findSite(id) {
  return loadSites().find(s => s.id === id)
}

// ── Helper: make authenticated request to WordPress ───────────────────────────
async function wpRequest(site, method, wpPath, data = null) {
  const base = site.url.replace(/\/$/, '')
  const url  = `${base}/wp-json/hoco-crm/v1${wpPath}`

  const config = {
    method,
    url,
    headers: {
      'Content-Type': 'application/json',
      'X-HOCO-API-Key': site.apiKey,
    },
    timeout: 15000,
  }
  if (data) config.data = data

  const res = await axios(config)
  return res.data
}

// ── Sites CRUD ─────────────────────────────────────────────────────────────────

// GET /api/sites
app.get('/api/sites', (req, res) => {
  res.json(loadSites())
})

// POST /api/sites
app.post('/api/sites', (req, res) => {
  const { name, url, apiKey, currency } = req.body
  if (!name || !url || !apiKey) return res.status(400).json({ error: 'name, url and apiKey required' })

  const sites = loadSites()
  const site  = { id: generateId(), name, url: url.replace(/\/$/, ''), apiKey, currency: currency || '₪', status: 'unknown', addedAt: new Date().toISOString() }
  sites.push(site)
  saveSites(sites)
  res.status(201).json(site)
})

// PATCH /api/sites/:id
app.patch('/api/sites/:id', (req, res) => {
  const sites = loadSites()
  const idx   = sites.findIndex(s => s.id === req.params.id)
  if (idx === -1) return res.status(404).json({ error: 'Not found' })
  sites[idx] = { ...sites[idx], ...req.body }
  saveSites(sites)
  res.json(sites[idx])
})

// DELETE /api/sites/:id
app.delete('/api/sites/:id', (req, res) => {
  const sites = loadSites().filter(s => s.id !== req.params.id)
  saveSites(sites)
  res.json({ deleted: true })
})

// ── Test connection ────────────────────────────────────────────────────────────
app.post('/api/test-connection', async (req, res) => {
  const { url, apiKey } = req.body
  try {
    const base = url.replace(/\/$/, '')
    await axios.get(`${base}/wp-json/hoco-crm/v1/reports/summary`, {
      headers: { 'X-HOCO-API-Key': apiKey },
      timeout: 8000,
    })
    res.json({ ok: true })
  } catch (e) {
    const msg = e.response?.status === 401 ? 'API Key שגוי' :
                e.response?.status === 404 ? 'פלאגין לא מותקן' :
                e.code === 'ECONNREFUSED' ? 'לא ניתן להתחבר לאתר' : e.message
    res.json({ ok: false, error: msg })
  }
})

// ── Proxy: all WP API calls ────────────────────────────────────────────────────
// GET  /api/sites/:id/proxy/*
// POST /api/sites/:id/proxy/*  etc.
app.all('/api/sites/:siteId/proxy/*', async (req, res) => {
  const site = findSite(req.params.siteId)
  if (!site) return res.status(404).json({ error: 'Site not found' })

  // Extract the WP path after /proxy
  const wpPath = '/' + req.params[0]

  // Append query string if present
  const qs = Object.keys(req.query).length
    ? '?' + new URLSearchParams(req.query).toString()
    : ''

  try {
    const data = await wpRequest(site, req.method, wpPath + qs, ['POST','PATCH','PUT'].includes(req.method) ? req.body : null)
    res.json(data)
  } catch (e) {
    const status = e.response?.status || 500
    const msg    = e.response?.data?.error || e.message
    res.status(status).json({ error: msg })
  }
})

// ── Health check ───────────────────────────────────────────────────────────────
app.get('/api/health', (req, res) => {
  const sites = loadSites()
  res.json({ ok: true, sites: sites.length, version: '1.0.0' })
})

// Periodically ping each site to update online status
async function pingAllSites() {
  const sites = loadSites()
  let changed = false
  for (const site of sites) {
    try {
      await axios.get(`${site.url.replace(/\/$/,'')}/wp-json/hoco-crm/v1/reports/summary`, {
        headers: { 'X-HOCO-API-Key': site.apiKey }, timeout: 5000,
      })
      if (site.status !== 'online') { site.status = 'online'; changed = true }
    } catch {
      if (site.status !== 'error') { site.status = 'error'; changed = true }
    }
  }
  if (changed) saveSites(sites)
}

setInterval(pingAllSites, 60_000) // ping every 60s
pingAllSites()                    // ping on startup

// ── Helper ────────────────────────────────────────────────────────────────────
function generateId() {
  return Math.random().toString(36).slice(2, 10) + Date.now().toString(36)
}

// ── Start ─────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`🚀 Ofnoacomps CRM Server running on http://localhost:${PORT}`)
  console.log(`   Sites config: ${SITES_FILE}`)
})
