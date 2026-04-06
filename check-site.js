const https = require('https');
const http  = require('http');

function checkSite(url) {
  const mod = url.startsWith('https') ? https : http;
  return new Promise((resolve) => {
    const req = mod.get(url, (res) => {
      let body = '';
      res.on('data', d => body += d);
      res.on('end', () => resolve({ status: res.statusCode, type: res.headers['content-type'], body: body.slice(0, 200) }));
    });
    req.on('error', e => resolve({ error: e.message }));
    req.end();
  });
}

async function main() {
  const sites = [
    'https://www.tryit.co.il/wp-json/hoco-crm/v1/reports/summary',
    'https://hoco-israel.co.il/wp-json/hoco-crm/v1/reports/summary',
  ];
  for (const url of sites) {
    console.log('\n🌐', url);
    const r = await checkSite(url);
    console.log('  STATUS:', r.status || r.error);
    console.log('  TYPE  :', r.type);
    console.log('  BODY  :', r.body);
  }
}
main();
