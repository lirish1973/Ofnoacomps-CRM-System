const https = require('https');

const url = 'https://www.hoco-israel.co.il/wp-json/hoco-crm/v1/reports/summary';

const req = https.get(url, { headers: { 'X-HOCO-API-Key': 'test' } }, (res) => {
  console.log('STATUS:', res.statusCode);
  let body = '';
  res.on('data', d => body += d);
  res.on('end', () => console.log('BODY:', body.slice(0, 300)));
});

req.on('error', e => console.log('ERROR:', e.message));
req.end();
