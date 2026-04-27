# CLAUDE MEMORY — ofnoacomps-crm
# עדכון אחרון: 2026-04-21

---

## פרטי בעל הפרוייקט
- **שם:** לירז (זכר)
- **אימייל:** ofnoacomps@gmail.com
- **GitHub user:** lirish1973

---

## פרטי פרוייקט CRM
- **שם:** ofnoacomps-crm
- **תיאור:** WordPress Plugin — מערכת CRM לניטור מבקרים, לידים ואנליטיקס בזמן אמת
- **נתיב מקומי:** C:\Users\ofnoa\ofnoacomps-crm
- **GitHub:** https://github.com/lirish1973/Ofnoacomps-CRM-System
- **גרסה נוכחית:** 1.4.1
- **Node.js proxy server:** localhost:3001

---

## Credentials / .env (gitignored)
- EMAIL_USER=ofnoacomps@gmail.com
- EMAIL_PASS= ← Gmail App Password — יש להזין!
- יצירה: https://myaccount.google.com/apppasswords
- בחר: Mail + Windows Computer → 16 תווים (ללא רווחים)

---

## Git — חשוב
- git.exe נמצא ב: C:\Program Files\Git\bin\git.exe
- git לא ב-PATH של Desktop Commander — תמיד להשתמש בנתיב המלא
- לדחיפה — להשתמש ב-.bat scripts עם shell: cmd

---

## Release Process
1. הרץ: release.bat
2. הכנס plugin: ofnoacomps-crm
3. הכנס version: X.X.X
4. הסקריפט עושה: עדכון גרסה + ZIP + manifest + git commit + auto-stash + push
5. אתרים מתעדכנים תוך שעה אוטומטית

---

## Auto-Update System
- Manifest URL: https://raw.githubusercontent.com/lirish1973/Ofnoacomps-CRM-System/main/plugin-updates.json
- Force check: ?ocrm_force_update_check=1 בכתובת האדמין
- Flush cache: POST /wp-json/ofnoacomps-crm/v1/flush-update-cache

---

## שינויים בסשן 2026-04-21

### tracker.js v3 — WooCommerce Tracking
- add_to_cart, remove_from_cart, view_cart, checkout_start, checkout_complete
- cart_abandonment via navigator.sendBeacon
- sessionStorage flags לניהול מצב עגלה

### class-analytics.php — get_ecommerce_summary()
- מחזיר: cart_adds, cart_views, checkout_starts, completions, abandonments, abandonment_rate

### REST API — /analytics/ecommerce
- Route חדש: GET /wp-json/ofnoacomps-crm/v1/analytics/ecommerce?days=30

### Dashboard.jsx — עיצוב ארגוני
- רענון אוטומטי 30 שניות + countdown timer + LiveBadge
- KPI rows: Traffic, CRM-Sales, WooCommerce
- Cart funnel, AreaChart, RecentLeads table

### plugin-updates.json — תוקן
- גרסה: 1.3.3 → 1.4.1
- תוקן encoding עברית מושחת
- last_updated: 2026-04-21

### build-plugin.yml — trigger paths תוקן
- נוסף: wordpress-plugin/ofnoacomps-crm/assets/**

### release-plugin.ps1 — שדרוגים
- Auto-stash לפני pull-rebase
- Python לעדכון manifest (שומר עברית UTF-8)

---

## פלאגין hoco-geo-seo

- **נתיב:** `wordpress-plugin/hoco-geo-seo/`
- **קובץ ראשי:** `hoco-geo-seo.php`
- **גרסה נוכחית:** 1.0.0
- **VERSION CONST:** `HOCO_GEO_SEO_VERSION`
- **מפתח ב-manifest:** `hoco-geo-seo`
- **ZIP:** `wordpress-plugin/hoco-geo-seo.zip`
- **עדכונים:** אוטומטי דרך `Ofnoacomps_GitHub_Updater` — אותו manifest כמו CRM
- **Release:** `.\release-plugin.ps1 -Plugin hoco-geo-seo -Version X.X.X`

פונקציונליות:
1. Organization Schema (כל עמוד)
2. WebSite + SearchAction Schema
3. Product Schema (עמודי מוצר WooCommerce)
4. ItemList Schema (עמוד הבית — 8 מוצרים אחרונים)
5. Canonical www enforcement
6. Security Headers: HSTS, X-Frame-Options, X-Content-Type, Referrer-Policy
7. www redirect 301 מ-non-www
8. llms.txt endpoint דינמי (`/llms.txt`)

---

## client-snippets/hoco-popup.php
לקוח: HOCO ישראל — פופ-אפ מועדון לקוחות

הגדרות לעדכן:
- HOCO_POPUP_DELAY: 10000 (ms)
- HOCO_WHATSAPP_NUM: 972501234567 ← עדכן!
- HOCO_NOTIFY_EMAIL: אוטומטי מ-admin_email

פונקציונליות:
1. פופ-אפ עם טופס: שם, אימייל, טלפון, הודעה, הסכמה
2. שולח WhatsApp אוטומטי
3. שומר ליד ב-WP כו-custom post type hoco_lead
4. שולח מייל התראה לאדמין
5. Admin: עמודות שם | אימייל | טלפון | הודעה | עמוד | תאריך

בעיות שתוקנו:
- Broken PHP→JS injection → wp_json_encode
- inset:0 → top/right/bottom/left:0 (Safari <14.1)
- gap → margin-left (Safari <14.1)
- -webkit-animation prefix
- e.key Escape + Esc + keyCode 27
- fetch() + XHR fallback
- Meta box + custom columns לכל הנתונים

---

## Git Commits בסשן זה
- 02a3dc4: sync dashboard, server, elementor, workflow
- d9b52d4: add client-snippets/hoco-popup.php
- e800f81: update release-plugin with auto-stash
- 83aa429: release ofnoacomps-crm v1.4.1

---

## API Endpoints
- GET  /wp-json/ofnoacomps-crm/v1/analytics/overview
- GET  /wp-json/ofnoacomps-crm/v1/analytics/ecommerce?days=30
- GET  /wp-json/ofnoacomps-crm/v1/leads
- POST /wp-json/ofnoacomps-crm/v1/track
- POST /wp-json/ofnoacomps-crm/v1/flush-update-cache

---

## DB Tables
ofnoacomps_leads, ofnoacomps_customers, ofnoacomps_pipelines,
ofnoacomps_stages, ofnoacomps_deals, ofnoacomps_activities,
ofnoacomps_deal_stage_log, ofnoacomps_lead_status_log,
ofnoacomps_events (עבור tracking: clicks, cart, pageviews)

---

## הערות חשובות
- PHP minimum: 7.4 | WordPress minimum: 5.8
- Dashboard: Vite + React + Tailwind + Recharts
- חובה להפעיל Node proxy לפני Dashboard: RUN_SERVER.bat
- אחרי כל שינוי קוד: release.bat עם גרסה חדשה
- GitHub Actions מופעל גם על assets (לא רק PHP)
