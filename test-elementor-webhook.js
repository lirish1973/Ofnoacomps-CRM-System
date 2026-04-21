#!/usr/bin/env node

/**
 * בדיקת Webhook של Elementor
 * 
 * שימוש:
 *   node test-elementor-webhook.js
 */

const http = require('http');

// נתוני ליד דוגמה
const testLeads = [
  {
    name: 'דוד כהן',
    email: 'david@example.com',
    phone: '0501234567',
    company: 'ABC Technologies',
    message: 'רוצה למעוד טיול ארצי'
  },
  {
    name: 'שרה לוי',
    email: 'sara@example.com',
    phone: '0509876543',
    source: 'Facebook Ad',
    message: 'שאלה על חבילות קבוצה'
  }
];

const CRM_ENDPOINT = 'http://localhost:3001/api/elementor/leads';

async function testWebhook(leadData) {
  return new Promise((resolve, reject) => {
    const url = new URL(CRM_ENDPOINT);
    const jsonData = JSON.stringify(leadData);

    const options = {
      hostname: url.hostname,
      port: url.port,
      path: url.pathname,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': jsonData.length
      }
    };

    console.log(`\n📤 שולח ליד: ${leadData.name}`);
    console.log(`📧 דוא"ל: ${leadData.email}`);
    console.log(`📞 טלפון: ${leadData.phone}`);

    const req = http.request(options, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        try {
          const response = JSON.parse(data);
          if (res.statusCode === 200) {
            console.log(`✅ הצלחה: ${response.message}`);
            console.log(`   ID: ${response.leadId}\n`);
          } else {
            console.log(`⚠️ Server returned ${res.statusCode}: ${response.message}\n`);
          }
          resolve(response);
        } catch (e) {
          console.log(`❌ שגיאה בפענוח תגובה: ${data}\n`);
          reject(e);
        }
      });
    });

    req.on('error', (error) => {
      console.log(`❌ שגיאה בחיבור: ${error.message}`);
      console.log(`   וודא שהשרת פועל: http://localhost:3001/api/health\n`);
      reject(error);
    });

    req.setTimeout(5000, () => {
      req.destroy();
      console.log(`❌ Timeout - השרת לא מגיב\n`);
      reject(new Error('Timeout'));
    });

    req.write(jsonData);
    req.end();
  });
}

async function runTests() {
  console.log('🧪 בדיקת Webhook של Elementor');
  console.log('================================\n');

  // בדוק חיבור ראשון
  console.log('🔍 בדיקה 1: חיבור לשרת CRM...');
  try {
    const healthRes = await fetch('http://localhost:3001/api/health');
    const health = await healthRes.json();
    console.log(`✅ השרת פעיל - גרסה: ${health.version}, אתרים: ${health.sites}\n`);
  } catch (e) {
    console.log(`❌ לא ניתן להתחבר לשרת!\n`);
    console.log('תקן את הבעיה:');
    console.log('1. וודא ש-Node.js מותקן');
    console.log('2. הרץ: npm install');
    console.log('3. הרץ את השרת: npm start');
    console.log('4. אז בדוק שוב\n');
    process.exit(1);
  }

  // שדור לידים
  console.log('📨 שליחת לידים לבדיקה...\n');

  let successCount = 0;
  let failureCount = 0;

  for (const lead of testLeads) {
    try {
      await testWebhook(lead);
      successCount++;
      await new Promise(r => setTimeout(r, 1000)); // השהיה בין בקשות
    } catch (e) {
      failureCount++;
    }
  }

  // סיכום
  console.log('════════════════════════════════════════════');
  console.log(`📊 תוצאות: ${successCount} הצליחו, ${failureCount} נכשלו`);
  console.log('════════════════════════════════════════════\n');

  // בדוק שנשמרו
  console.log('🔍 בדיקה 2: הורדת כל הלידים...');
  try {
    const leadsRes = await fetch('http://localhost:3001/api/elementor/leads');
    const leadsData = await leadsRes.json();
    console.log(`✅ סה"כ לידים בשרת: ${leadsData.count}\n`);

    if (leadsData.leads.length > 0) {
      console.log('📋 הלידים האחרונים:');
      leadsData.leads.slice(-3).forEach((lead, i) => {
        console.log(`  ${i + 1}. ${lead.data.name || 'ללא שם'} - ${lead.timestamp.slice(0, 10)}`);
      });
    }
  } catch (e) {
    console.log('❌ לא ניתן להורדת רשימת הלידים\n');
  }

  console.log('\n✨ בדיקה הסתיימה!');
  console.log('\n🎯 שלב הבא:');
  console.log('1. הוסף את URL הזה לטופס Elementor:');
  console.log(`   ${CRM_ENDPOINT}`);
  console.log('2. בדוק בעדכון דוא"ל: ofnoacomps@gmail.com');
  console.log('3. אם לא קיבלת דוא"ל, בדוק:');
  console.log('   - EMAIL_PASS environment variable מוגדר?');
  console.log('   - Gmail App Password נוצר?');
}

// Run
runTests().catch(console.error);
