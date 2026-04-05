# 🏢 Ofnoacomps CRM System

מערכת ניהול לקוחות (CRM) מרכזית שרצה **מקומית על המחשב** ומתחברת לכמה אתרי WordPress בו-זמנית.

---

## ✨ תכונות

- 🌐 **Multi-site** — כל אתר WordPress בכרטיסייה נפרדת בצד שמאל
- 👥 **ניהול לידים** — סינון, מיון, עדכון סטטוס, לוח פעילויות
- 🏢 **ניהול לקוחות** — מאגר לקוחות עם עסקאות ופעילויות
- 🔄 **Pipeline / Kanban** — ניהול עסקאות בלוח גרירה
- 📊 **דוחות** — גרפים, הכנסות, מקורות תנועה, דירוג נציגים
- 🔑 **API Key auth** — חיבור מאובטח לכל אתר WordPress
- 📡 **Live status** — פינג אוטומטי לכל אתר בכל דקה

---

## 🗂 ארכיטקטורה

```
[Local Machine — localhost:5173]
       │
       ▼
React Dashboard (Vite)
       │  /api/*
       ▼
Node.js Proxy Server (localhost:3001)
       │  X-HOCO-API-Key
       ▼
WordPress Site 1          WordPress Site 2          ...
/wp-json/hoco-crm/v1/    /wp-json/hoco-crm/v1/
```

---

## 🚀 התקנה והפעלה

### דרישות
- Node.js 18+
- npm 9+
- WordPress עם פלאגין **Hoco CRM** מותקן בכל אתר

### שלבי התקנה

```bash
# 1. שכפל את הריפו
git clone https://github.com/ofnoacomps/Ofnoacomps-CRM-System.git
cd Ofnoacomps-CRM-System

# 2. התקן תלויות
npm install

# 3. הפעל (שרת + פרונט-אנד ביחד)
npm run dev
```

פתח את הדפדפן על: **http://localhost:5173**

---

## 🔗 חיבור אתר WordPress

### שלב 1 — התקן את פלאגין Hoco CRM
- הורד את `hoco-crm.zip` מתיקיית `/wordpress-plugin`
- WordPress Admin → Plugins → Upload Plugin
- הפעל את הפלאגין

### שלב 2 — צור API Key
- WordPress Admin → **Hoco CRM → הגדרות**
- לחץ **"+ צור API Key חדש"**
- העתק את ה-Key (מוצג פעם אחת בלבד!)

### שלב 3 — הוסף את האתר ב-Dashboard
- לחץ **"+ הוסף אתר"** בסרגל הצד
- הזן: שם אתר, URL, ו-API Key
- לחץ **"🔌 בדוק חיבור"** לווידוא
- לחץ **"הוסף אתר"**

---

## 📁 מבנה פרויקט

```
ofnoacomps-crm/
├── server/
│   ├── index.js          # Express proxy server
│   └── sites.json        # הגדרות האתרים (לא נשמר ב-git)
├── src/
│   ├── App.jsx            # Root component
│   ├── components/
│   │   ├── Sidebar.jsx    # רשימת אתרים
│   │   ├── SiteView.jsx   # תצוגת אתר עם טאבים
│   │   ├── Dashboard.jsx  # דשבורד + גרפים
│   │   ├── Leads.jsx      # ניהול לידים
│   │   ├── Customers.jsx  # ניהול לקוחות
│   │   ├── Pipeline.jsx   # Kanban board
│   │   ├── Reports.jsx    # דוחות ואנליטיקה
│   │   └── SiteSettings.jsx # הגדרות אתר
│   └── hooks/
│       └── useApi.js      # API hook
├── wordpress-plugin/
│   └── hoco-crm.zip       # פלאגין WordPress
└── package.json
```

---

## 🔐 אבטחה

- ה-API Keys מאוחסנים **מוצפנים** (SHA-256) ב-WordPress
- הפלאגין מאמת כל בקשה דרך header `X-HOCO-API-Key`
- ה-keys מאוחסנים ב-`server/sites.json` — **לא נשמרים ב-git**
- כל התקשורת דרך HTTPS (בסביבת ייצור)

---

## 📜 REST API

```
Base URL: https://your-site.com/wp-json/hoco-crm/v1/

Authentication: X-HOCO-API-Key: hoco_xxxxxxxxxx

GET  /leads                  # רשימת לידים
POST /leads                  # צור ליד
GET  /leads/:id              # קבל ליד
POST /leads/:id/convert      # המר ללקוח
GET  /customers              # רשימת לקוחות
GET  /deals/kanban           # Kanban board
GET  /reports/summary        # סיכום KPIs
GET  /reports/leads-by-source  # לפי מקור
GET  /reports/pipeline-funnel  # פאנל
```

---

## 🛠 פיתוח

```bash
npm run client   # React Vite (port 5173)
npm run server   # Node.js Express (port 3001)
npm run dev      # שניהם ביחד עם concurrently
npm run build    # בנה לייצור
```

---

## 📄 רישיון

MIT © Ofnoacomps 2026
