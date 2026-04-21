# 📧 אוטומציה לידים - סיכום הגדרה

## ✅ מה שהוקם עבורך

### 1️⃣ Server Endpoint
**קובץ:** `server/index.js`  
**Endpoint:** `POST http://localhost:3001/api/elementor/leads`

זה קבל לידים מטופסים שונים ושולח למייל.

### 2️⃣ קובצי עזר שנוצרו
```
server/
├── index.js                    ✅ השרת הראשי + קוד החדש
├── elementor-webhook.php       ✅ PHP webhook ל-WordPress
├── elementor-lead-handler.js   ✅ JavaScript handler בצד הלקוח
├── README-LEADS.md             ✅ תיעוד טכני מלא
└── [קבצים נוספים]

root/
├── ELEMENTOR_SETUP.md          ✅ הוראות הגדרה משלב לשלב
├── test-elementor-webhook.js   ✅ בדיקה אוטומטית
└── start-leads-server.bat      ✅ קובץ הפעלה (Windows)

_leads/                         📁 תיקייה לשמירת לידים
```

---

## 🚀 כיצד להתחיל (מהר!)

### **שלב 1: התקנה (חד פעמית)**

```bash
cd C:\Users\ofnoa\ofnoacomps-crm
npm install nodemailer
```

### **שלב 2: הפעלת השרת**

**אפשרות א - קובץ batch (הפשוטה):**
```
כפול לחץ על: start-leads-server.bat
```

**אפשרות ב - Command Line:**
```bash
npm start
```

**בדיקה שהשרת פועל:**
```bash
curl http://localhost:3001/api/health
```

תגובה צפויה:
```json
{"ok": true, "sites": 1, "version": "1.1.0"}
```

### **שלב 3: הגדר Elementor**

בעורך Elementor של הטופס שלך:
1. בחר את הטופס
2. עבור ל-**Actions After Submit**
3. בחר **Send Webhook** או **Custom Webhook**
4. הכנס את ה-URL:
   ```
   http://localhost:3001/api/elementor/leads
   ```
5. Method: **POST**
6. שמור

### **שלב 4: בדיקה**

```bash
node test-elementor-webhook.js
```

זה יהיה שולח 2 לידים דוגמה וידפיס אם הכל עובד.

---

## 📧 דוא"ל (Gmail Setup)

### איתחול חד-פעמי

1. עבור ל: **https://myaccount.google.com/security**
2. חפש: **App passwords** (יופיע רק אם יש 2FA)
3. בחר: **Mail** → **Windows Computer**
4. העתק את 16 הספרות (סיסמה)

### הגדרה בשרת

**Windows PowerShell:**
```powershell
$env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"
```

**Windows Command Prompt:**
```cmd
set EMAIL_PASS=xxxx xxxx xxxx xxxx
```

**Linux/Mac:**
```bash
export EMAIL_PASS="xxxx xxxx xxxx xxxx"
```

או בקובץ `.env`:
```
EMAIL_USER=ofnoacomps@gmail.com
EMAIL_PASS=xxxx xxxx xxxx xxxx
```

---

## 📬 איך זה עובד

```
טופס Elementor
       ↓
    submit
       ↓
POST: http://localhost:3001/api/elementor/leads
       ↓
  Node.js Server
       ↓
  [וודא נתונים]
       ↓
  nodemailer + Gmail API
       ↓
📧 Email to: ofnoacomps@gmail.com
   Subject: ליד מכירה מאתר TRYIT
       ↓
[שמור קובץ JSON בתיקיית _leads/]
```

---

## 🔧 דוגמה לשליחת ליד ידנית

### cURL
```bash
curl -X POST http://localhost:3001/api/elementor/leads \
  -H "Content-Type: application/json" \
  -d '{
    "name": "דוד כהן",
    "email": "david@example.com",
    "phone": "0501234567",
    "message": "מעניין בטיול"
  }'
```

### JavaScript
```javascript
fetch('http://localhost:3001/api/elementor/leads', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'דוד כהן',
    email: 'david@example.com',
    phone: '0501234567'
  })
})
.then(r => r.json())
.then(d => console.log('✅', d.message));
```

---

## 📊 ניהול לידים

### קבלת כל הלידים
```bash
curl http://localhost:3001/api/elementor/leads
```

### צפייה בקבצים המקומיים
```bash
ls -la _leads/
```

### צפייה בלוג
```bash
tail -f _leads_log.txt
```

---

## ⚠️ בעיות נפוצות

### ❌ "Port 3001 already in use"
```bash
# מצא תהליך על פורט 3001
netstat -ano | findstr :3001

# הרוג אותו (תחליף PID בתוצאה)
taskkill /PID <PID> /F
```

### ❌ "nodemailer not found"
```bash
npm install nodemailer
```

### ❌ "Email not received"
1. ✅ בדוק: `echo $env:EMAIL_PASS` (צריך להראות משהו)
2. ✅ בדוק את Gmail Spam
3. ✅ וודא שApp Password נוצר (לא סיסמה רגילה)
4. ✅ בדוק console של Node לשגיאות

### ❌ "CORS error בדפדפן"
זה בסדר - webhooks עובדים בצד השרת, לא הדפדפן.
שגיאת CORS לא משפיעה על פונקציונליות.

---

## 🛠️ הרחבות (עתידיות)

### שלח גם ל-Slack
```javascript
// בקרוב - הוסף webhook של Slack
const slackWebhook = 'https://hooks.slack.com/...';
```

### שלח ל-WhatsApp
```javascript
// בקרוב - integrat עם Twilio
const twilioClient = require('twilio')(...);
```

### שמור בדטה בייס
```javascript
// בקרוב - MongoDB או PostgreSQL
const db = require('mongoose');
```

---

## 📁 מבנה הקבצים

```
C:\Users\ofnoa\ofnoacomps-crm\
├── server/
│   ├── index.js                    ← השרת הראשי
│   ├── elementor-webhook.php       ← PHP webhook
│   ├── elementor-lead-handler.js   ← JS handler
│   ├── README-LEADS.md             ← תיעוד
│   └── sites.json                  ← הגדרות אתרים
│
├── src/                            ← Frontend
├── public/                         ← Assets
├── node_modules/                   ← ספריות
│
├── ELEMENTOR_SETUP.md              ← הוראות מלאות
├── LEADS_SETUP_SUMMARY.md          ← קובץ זה
├── test-elementor-webhook.js       ← בדיקה
├── start-leads-server.bat          ← הפעלה
│
├── _leads/                         ← שמירת לידים
├── _leads_log.txt                  ← לוג
│
└── package.json                    ← Dependencies
```

---

## 🎯 רשימת TODO

- [ ] הפעל את `start-leads-server.bat`
- [ ] בדוק ש-Node.js בPATH
- [ ] הגדר EMAIL_PASS
- [ ] הרץ `test-elementor-webhook.js`
- [ ] בדוק קבלת דוא"ל
- [ ] הגדר Elementor webhook
- [ ] בדוק טופס בפועל
- [ ] בדוק את קובצי הלידים ב-`_leads/`

---

## 📞 תמיכה

**בעיות?**
1. קרא את `ELEMENTOR_SETUP.md`
2. קרא את `server/README-LEADS.md`
3. הרץ `test-elementor-webhook.js`
4. בדוק console של Node
5. בדוק Firewall

---

## ✨ מה שהסתיים

```
✅ Node.js Server                  כתוב וממשק
✅ Elementor integration          מוכן
✅ Gmail sending                  מוכן (צריך PASSWORD)
✅ Lead storage                   מוכן (_leads/)
✅ Webhook handler                מוכן (PHP)
✅ JS client library              מוכן
✅ Documentation                  מוכן
✅ Testing script                 מוכן
✅ Startup batch file             מוכן
```

---

**Version:** 1.1.0  
**Created:** 2026-04-16  
**Status:** ✅ Ready to Use  
**Next Step:** הפעל את start-leads-server.bat
