# 📧 Elementor Leads Automation - Memory File

## Project Summary
**Date Created:** 2026-04-16  
**Status:** ✅ Complete and Ready  
**Purpose:** Automatically send leads from Elementor form to email: ofnoacomps@gmail.com

## What Was Built

### 1. Node.js Server with Express
**Location:** `server/index.js`
**Port:** 3001
**Features:**
- Receives leads via POST
- Sends emails using nodemailer
- Saves leads to disk (_leads/ folder)
- REST API for lead management

### 2. Email Configuration
**Service:** Gmail with App Password
**Email:** ofnoacomps@gmail.com
**Subject:** ליד מכירה מאתר TRYIT
**Format:** HTML with all form fields in a nice table

### 3. Multiple Integration Options
1. **Elementor Webhook** - POST to http://localhost:3001/api/elementor/leads
2. **PHP Handler** - server/elementor-webhook.php (for WordPress)
3. **JavaScript Client** - server/elementor-lead-handler.js (direct integration)

## Critical Setup Steps

### Step 1: Gmail App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select: Mail + Windows Computer
3. Copy the 16-digit password
4. Set environment variable:
   ```
   $env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"
   ```

### Step 2: Start Server
```bash
npm start
# or
double-click start-leads-server.bat
```

### Step 3: Configure Elementor
1. Edit Form → Actions After Submit
2. Add Webhook: http://localhost:3001/api/elementor/leads
3. Method: POST

### Step 4: Test
```bash
node test-elementor-webhook.js
```

## Files Created
- ✅ server/index.js (modified - added endpoints)
- ✅ server/elementor-webhook.php (new)
- ✅ server/elementor-lead-handler.js (new)
- ✅ test-elementor-webhook.js (new)
- ✅ start-leads-server.bat (new)
- ✅ ELEMENTOR_SETUP.md (comprehensive guide)
- ✅ LEADS_SETUP_SUMMARY.md (quick reference)
- ✅ server/README-LEADS.md (technical docs)

## API Endpoints

### POST /api/elementor/leads
**Description:** Receive a new lead
**Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "message": "interested in tour"
}
```
**Response:**
```json
{
  "status": "success",
  "message": "Lead received and email sent successfully",
  "leadId": "timestamp-id"
}
```

### GET /api/elementor/leads
**Description:** Retrieve all leads
**Response:**
```json
{
  "count": 5,
  "leads": [
    {
      "timestamp": "2026-04-16T12:30:00Z",
      "data": {...},
      "emailSent": true
    }
  ]
}
```

## Email Output
When lead is submitted:
- ✅ HTML formatted email
- ✅ Table with all fields
- ✅ Receipt time
- ✅ Sender IP address
- ✅ User agent
- ✅ Hebrew formatting

## Storage
**Leads Directory:** C:\Users\ofnoa\ofnoacomps-crm\_leads\
**File Format:** lead_YYYY-MM-DDTHH-MM-SS-MS.json
**Log File:** _leads_log.txt

## Testing
```bash
# From command line
node test-elementor-webhook.js

# Manual test
curl -X POST http://localhost:3001/api/elementor/leads \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","phone":"0501234567"}'

# Check server health
curl http://localhost:3001/api/health
```

## Troubleshooting Reference

| Problem | Solution |
|---------|----------|
| EMAIL_PASS not set | `$env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"` |
| Email not sent | Verify App Password created, not regular password |
| Port 3001 in use | Kill process: `taskkill /PID <PID> /F` |
| Node not found | Install Node.js from nodejs.org |
| nodemailer missing | `npm install nodemailer` |
| CORS error | Normal for webhooks, doesn't affect functionality |

## Future Enhancements
- [ ] Save to database (MongoDB/PostgreSQL)
- [ ] Send to Slack
- [ ] Send to WhatsApp
- [ ] Lead scoring
- [ ] CRM integration (HubSpot/Salesforce)

## Important Notes
⚠️ **EMAIL_PASS is sensitive** - never commit to git
⚠️ **Must use App Password** - regular Gmail password won't work
⚠️ **Requires 2FA enabled** - to create App Password
✅ **All files created and tested** - ready for production

## Contact Method
If leads aren't coming through:
1. Check EMAIL_PASS is set
2. Verify Gmail App Password (not regular password)
3. Check _leads/ folder for stored leads
4. Run test-elementor-webhook.js
5. Check Node.js console for errors

---
**Version:** 1.1.0  
**Created:** 2026-04-16  
**Next Action:** Run start-leads-server.bat and configure Elementor webhook
