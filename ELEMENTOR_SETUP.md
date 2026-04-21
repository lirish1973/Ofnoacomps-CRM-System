# 📧 הגדרת אוטומציה לידים מ-Elementor

## סקירה
כאשר משהו מגיש טופס ב-Elementor, הליד יישלח אוטומטית למייל שלך: **ofnoacomps@gmail.com**

---

## דרך 1: Webhook ישירה (המומלצת)

### שלב 1: הגדר את דואר ה-Gmail
1. פתח את [Google Account Security](https://myaccount.google.com/security)
2. עבור ל-"App passwords" (אם יש לך 2FA מופעל)
3. יצור סיסמה לאפליקציה ל-"Mail"
4. העתק את הסיסמה

### שלב 2: הגדר Environment Variable בשרת
בשורת פקודה:
```bash
$env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"
```

או ב-Windows:
```
set EMAIL_PASS=xxxx xxxx xxxx xxxx
```

### שלב 3: צרוף את Webhook ל-Elementor
בעורך Elementor של הטופס:
1. פתח את **Elementor Editor**
2. עדיין על הטופס, לחץ **Edit Form**
3. בחר את הטופס שלך
4. בכרטיסייה **Actions After Submit**
5. בחר **Send Email** או **Webhook**
6. אם יש אפשרות **Custom Webhook**:
   - **URL:** `http://localhost:3001/api/elementor/leads`
   - **Method:** POST
   - **Data Type:** JSON

### שלב 4: בדוק שהכל עובד
1. כתוב משהו בטופס ודחוף Submit
2. בדוק את דואר אלקטרוני בעוד דקה או שתיים

---

## דרך 2: עם קובץ PHP ב-WordPress (אם השרת המקומי לא פעיל)

### שלב 1: העתק את הקובץ לשרת
```
/wp-content/plugins/ofnoacomps-crm/elementor-webhook.php
```

### שלב 2: בעורך Elementor
בטופס, הוסף:
- **Custom Webhook URL:** `https://www.tryit.co.il/wp-content/plugins/ofnoacomps-crm/elementor-webhook.php`
- **Method:** POST

### שלב 3: בדוק את הלוג
```
/wp-content/plugins/ofnoacomps-crm/../_leads_log.txt
```

---

## דרך 3: שימוש בתוסף Elementor (לא חינמי)

אם יש לך Elementor Pro:
1. בעורך הטופס, לחץ על Settings
2. בחר **Actions After Submit → Email**
3. בחר את כתובת המייל: **ofnoacomps@gmail.com**
4. כותרת: **ליד מכירה מאתר TRYIT**
5. Save

---

## אפשרויות שדות בטופס

כשמוגדר webhook, כל שדה בטופס יישלח למייל:

| שם שדה | תיאור |
|--------|-------|
| `name` / `Full Name` | שם המוביל |
| `email` / `Email` | כתובת דוא"ל |
| `phone` / `Phone` | מספר טלפון |
| `message` / `Message` | הודעה / הערה |
| `company` / `Company` | חברה |
| כל שדה אחר | יישלח כפי שהוא |

---

## בדיקת חיבור

### בשרת המקומי:
```bash
curl -X POST http://localhost:3001/api/elementor/leads \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","phone":"0501234567"}'
```

### קבלת כל הלידים:
```bash
curl http://localhost:3001/api/elementor/leads
```

---

## טיפול בבעיות

### הליד לא נשלח
1. ✅ בדוק שהשרת פועל: `http://localhost:3001/api/health`
2. ✅ בדוק שיש סיסמה: `echo $env:EMAIL_PASS`
3. ✅ בדוק לוגים: `_leads` תיקייה

### Gmail לא קובל את הסיסמה
- אם יש לך 2FA, צריך App Password
- אל תשתמש בסיסמה הרגילה שלך
- [יצירת App Password](https://myaccount.google.com/apppasswords)

### הודעה על אישור לא מגיעה
- אם ה-Webhook לא מגיב, Elementor יציג "Error"
- בדוק את console ב-browser
- בדוק את הلוג בשרת

---

## שדירוג בעתיד

### לשלוח ל-CRM טבלה
```javascript
// במקום פשוט למייל, שמור בדטה בייס
POST /api/leads/save
```

### שלח גם ל-WhatsApp
```javascript
// Twilio API
POST /api/leads/whatsapp
```

### סינכרוניזציה עם Stripe
```javascript
// אם רוצה לשמור גם כ-Customer
POST /api/leads/create-customer
```

---

## דוגמה של טופס עם Webhook

```html
<form id="elementor-lead-form">
  <input type="text" name="name" placeholder="שם מלא" required>
  <input type="email" name="email" placeholder="דוא\"ל" required>
  <input type="tel" name="phone" placeholder="טלפון" required>
  <textarea name="message" placeholder="הודעה"></textarea>
  <button type="submit">שלח</button>
</form>

<script>
document.getElementById('elementor-lead-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = Object.fromEntries(formData);
  
  const response = await fetch('http://localhost:3001/api/elementor/leads', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  
  const result = await response.json();
  alert(result.message);
});
</script>
```

---

## ניתוק אוטומציה

כדי להפסיק שליחת לידים:
1. בעורך Elementor, הסר את ה-Webhook
2. או עצור את השרת: `Ctrl+C`
3. בקובץ PHP, הערה את ה-curl request

---

**עדכון אחרון:** 2026-04-16
**גרסה:** 1.1.0
**סטטוס:** ✅ פעיל
