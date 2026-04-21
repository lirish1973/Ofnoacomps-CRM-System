# 📧 CRM Lead Management System

## סקירה כללית
מערכת זו מנהלת לידים מטופסים שונים וקובעת אוטומטיות לשליחתם למייל.

## Endpoints זמינים

### 📮 קבלת ליד חדש
**POST** `/api/elementor/leads`

```bash
curl -X POST http://localhost:3001/api/elementor/leads \
  -H "Content-Type: application/json" \
  -d '{
    "name": "דוד כהן",
    "email": "david@example.com",
    "phone": "0501234567",
    "message": "רוצה למעוד טיול"
  }'
```

**תגובה בהצלחה:**
```json
{
  "status": "success",
  "message": "Lead received and email sent successfully",
  "leadId": "2026-04-16t143022-123abc"
}
```

### 📋 הורדת כל הלידים
**GET** `/api/elementor/leads`

```bash
curl http://localhost:3001/api/elementor/leads
```

**תגובה:**
```json
{
  "count": 5,
  "leads": [
    {
      "timestamp": "2026-04-16T14:30:22.123Z",
      "data": {
        "name": "דוד כהן",
        "email": "david@example.com",
        "phone": "0501234567"
      },
      "emailSent": true
    }
  ]
}
```

---

## הגדרה ראשונית

### 1. התקנת תלויות
```bash
npm install
npm install nodemailer  # אם עדיין לא מותקן
```

### 2. הגדרת דוא"ל
בדיוק כמו שעשית עם Gmail, יצור App Password:

```bash
# Windows
$env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"

# macOS/Linux
export EMAIL_PASS="xxxx xxxx xxxx xxxx"
```

### 3. הפעל את השרת
```bash
npm start
# או
node index.js
```

### 4. בדוק שהכל עובד
```bash
node test-elementor-webhook.js
```

---

## מבנה נתונים

### Lead Object
```javascript
{
  timestamp: "2026-04-16T12:30:45.123Z",
  data: {
    name: String,
    email: String,
    phone: String,
    message: String,
    company: String,
    source: String,
    // כל שדה אחר מהטופס
    [customField]: Any
  },
  emailSent: Boolean
}
```

### Storage
- **לידים שמורים בתיקייה:** `_leads/`
- **שם הקובץ:** `lead_YYYY-MM-DDTHH-MM-SS-MS.json`
- **עותק הלוג:** `_leads_log.txt`

---

## איימייל שנשלח

כל ליד מוביל למייל HTML עם:
- ✅ כותרת: "ליד מכירה מאתר TRYIT"
- ✅ כל השדות בטבלה מעוצבת
- ✅ זמן הקבלה
- ✅ כתובת ה-IP של השולח
- ✅ User Agent
- ✅ תשובה ל-Reply-To כתובת המשלוח

---

## מפתחות סביבה

| מפתח | תיאור | ברירת מחדל |
|------|-------|------------|
| `EMAIL_USER` | כתובת דוא"ל לשליחה | ofnoacomps@gmail.com |
| `EMAIL_PASS` | סיסמת אפליקציה Gmail | (חובה להגדרה!) |
| `PORT` | פורט השרת | 3001 |

---

## אפשרויות שילוב

### 1. Elementor Pro (קל ביותר)
בחר את URL של ה-webhook בטופס.

### 2. Elementor Free + Custom Webhook
בטופס בחר "Custom Webhook" והוסף את ה-endpoint.

### 3. Gravity Forms / WPForms
```javascript
addAction('gform_after_submission', function(entry, form) {
  fetch('http://localhost:3001/api/elementor/leads', {
    method: 'POST',
    body: JSON.stringify(entry.data)
  });
});
```

### 4. JavaScript ישיר בעמוד
```html
<script src="server/elementor-lead-handler.js"></script>
<script>
window.CRMLeadHandler.sendLead({name: "שם", email: "email@example.com"});
</script>
```

---

## בעיות נפוצות

### ❌ "Connection refused"
```
תיקון:
1. וודא ש-npm start הורץ בהצלחה
2. בדוק שאין program אחר על פורט 3001
3. בדוק firewall settings
```

### ❌ "Email not sent" אבל ליד נקלט
```
תיקון:
1. וודא ש-EMAIL_PASS מוגדר
   echo $env:EMAIL_PASS
2. בדוק אם Gmail App Password נוצר
3. בדוק Spam/Promotions בGmail
4. צפה ב-console של Node לשגיאות
```

### ❌ "Method not allowed"
```
התיקון: השתמש בPOST כששולח לידים, GET כשמורידים
```

### ❌ CORS errors בדפדפן
```
זה בסדר - הleads יישלחו בשרת.
אם רוצה לתקן:
- הוסף origin בעורך Elementor
- או בדוק את CORS בindex.js
```

---

## ניטור

### הצפייה בלידים האחרונים
```bash
ls -lah _leads/ | tail -10
```

### צפייה בלוג
```bash
tail -f _leads_log.txt
```

### בדיקה של כל הלידים
```bash
curl http://localhost:3001/api/elementor/leads | python -m json.tool
```

---

## בטיחות

### הגנה על סיסמה
- ❌ **אל תשמור** את EMAIL_PASS בקובץ
- ❌ **אל תשלח** בURL או בקוד
- ✅ **השתמש** בEnvironment Variables
- ✅ **הגבל** גישה לקובץ ה-leads

### HTTPS בייצור
```bash
# בשרת באינטרנט, השתמש ב-HTTPS
const https = require('https');
const fs = require('fs');

const options = {
  key: fs.readFileSync('private-key.pem'),
  cert: fs.readFileSync('certificate.pem')
};

https.createServer(options, app).listen(3001);
```

### Rate Limiting
```javascript
const rateLimit = require('express-rate-limit');
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100 // max 100 leads per 15 min
});

app.post('/api/elementor/leads', limiter, handler);
```

---

## הרחבות עתידיות

- [ ] שמירה בדטה בייס (MongoDB, PostgreSQL)
- [ ] Webhook מותנה על בסיס תוכן
- [ ] שליחה ל-Slack, WhatsApp, Telegram
- [ ] CRM Integration (HubSpot, Salesforce)
- [ ] Analytics Dashboard
- [ ] Lead Scoring

---

## Support

בעיות? עזרה תוכל לקבל ב:
- בדוק את ה-console של Node
- קרא את `ELEMENTOR_SETUP.md`
- הרץ את `test-elementor-webhook.js`

**Version:** 1.1.0  
**Last Updated:** 2026-04-16  
**Status:** ✅ Production Ready
