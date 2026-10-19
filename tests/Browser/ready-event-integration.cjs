const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(path.join(process.env.TEMP, 'rsvp-browser-tools/node_modules/playwright'));
const credentials = JSON.parse(fs.readFileSync(path.join(process.env.TEMP, 'rsvp-ready-browser/credentials.json'), 'utf8'));
const output = path.resolve(__dirname, '../../MDFiles/ready-event-qa');
fs.mkdirSync(output, { recursive: true });
let stage = 'startup';
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
 const context = await browser.newContext({ reducedMotion: 'reduce', timezoneId: 'America/Los_Angeles' });
 const page = await context.newPage();
 page.setDefaultTimeout(15000); page.setDefaultNavigationTimeout(20000);
 const errors = [], checks = [];
 page.on('pageerror', error => errors.push(error.name));
 async function open() {
  await page.getByRole('heading', { name: 'QA Christening and Birthday', exact: true }).waitFor();
  await page.getByRole('button', { name: 'You Are Invited', exact: false }).click();
  await page.locator('#event-title').waitFor({ state: 'visible' });
 }
 try {
  stage = 'public responsive';
  for (const width of [320,375,390,768,1024,1440]) {
   await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
   await page.goto('http://127.0.0.1:5176/');
   await page.locator('#landing-title').filter({ hasText: 'QA Christening and Birthday' }).waitFor();
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
   await open();
   assert.equal(await page.locator('#details-heading').textContent(), 'All the little details');
   assert.equal(await page.locator('.detail-primary').allTextContents().then(values => values.includes('QA Restaurant')), true);
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
   assert.equal(await page.locator('.deadline').textContent(), 'Please reply by January 9, 2027.');
   assert.equal(await page.locator('body').innerText().then(text => text.includes('Asia/Manila')), false);
   await page.locator('.baby-carousel').scrollIntoViewIfNeeded();
   await page.waitForFunction(() => [...document.querySelectorAll('.baby-carousel-photo-button img')].every(img => img.complete && img.naturalWidth > 0));
   if ([390,1440].includes(width)) await page.screenshot({ path: path.join(output, 'invitation-'+width+'.png'), fullPage: true });
  }
  checks.push('live root landing/invitation and two venues at six widths; event-local deadline in US browser timezone');
  stage = 'actual RSVP';
  await page.locator('input[name="full_name"]').fill('QA Browser Guest');
  await page.getByRole('button', { name: 'Send my RSVP', exact: false }).click();
  await page.getByText('Your RSVP has been saved.', { exact: true }).waitFor();
  const key = await page.evaluate(() => localStorage.getItem('rsvp-response:qa-ready'));
  assert.equal(typeof key, 'string');
  await page.getByRole('button', { name: /change/i }).click();
  await page.locator('input[name="full_name"]').fill('QA Browser Guest');
  await page.getByText('Regretfully declines', { exact: true }).click();
  await page.getByRole('button', { name: 'Send my RSVP', exact: false }).click();
  await page.getByText('Your RSVP has been saved.', { exact: true }).waitFor();
  assert.equal(await page.evaluate(() => localStorage.getItem('rsvp-response:qa-ready')), key);
  checks.push('actual attending RSVP and declined update retain same response identity');
  stage = 'admin form';
  await page.goto('http://127.0.0.1:8016/admin/login');
  await page.getByLabel('Username or email').fill('qa-ready');
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL('http://127.0.0.1:8016/admin');
  for (const width of [320,375,390,768,1024,1440]) {
   await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
   await page.goto('http://127.0.0.1:8016/admin/event-settings/'+credentials.event_id+'/edit');
   assert.equal(await page.locator('input[name="timezone"]').count(), 0);
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
   if ([390,1440].includes(width)) await page.screenshot({ path: path.join(output, 'event-form-'+width+'.png'), fullPage: true });
  }
  await page.getByRole('button', { name: 'Remove occasion', exact: true }).click();
  assert.equal(await page.locator('#second-occasion').isVisible(), false);
  await page.getByRole('button', { name: 'Add occasion', exact: true }).click();
  assert.equal(await page.locator('#occasion-1-label').evaluate(el => el === document.activeElement), true);
  await page.getByRole('button', { name: 'Remove occasion', exact: true }).click();
  await page.getByRole('button', { name: 'Save event settings', exact: true }).click();
  await page.getByText('Event settings saved. Published invitations use these details.', { exact: true }).waitFor();
  assert.equal((await (await context.request.get('http://127.0.0.1:8016/api/website-event')).json()).event.event_stops.length, 1);
  checks.push('admin form six widths, automatic timezone, Add/Remove focus and saved second-occasion removal');
  stage = 'direct refresh';
  await page.goto('http://127.0.0.1:5176/qa-ready'); await open();
  assert.equal(await page.locator('.detail-primary').count(), 1);
  await page.reload(); await open();
  checks.push('direct slug and browser refresh preserve saved one-occasion event');
  stage = 'unavailable';
  await page.route('**/api/website-event', route => route.fulfill({ status: 404, contentType: 'application/json', body: '{"message":"Not Found"}' }));
  await page.goto('http://127.0.0.1:5176/');
  await page.getByRole('heading', { name: 'Invitation unavailable', exact: true }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'You Are Invited', exact: false }).isDisabled(), true);
  await page.unroute('**/api/website-event');
  await page.getByRole('button', { name: 'Try again', exact: true }).click(); await open();
  checks.push('unavailable state disables entry; retry reloads real configured event');
  assert.deepEqual(errors, []); checks.push('no browser JavaScript errors');
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ environment: 'isolated SQLite, actual React/Laravel requests, reduced motion, US visitor timezone', checks }, null, 2));
  console.log(JSON.stringify({ passed: checks.length, checks }));
 } finally { await browser.close(); }
})().catch(error => { console.error(JSON.stringify({ stage, failure: error.name, location: error.stack?.split('\n').find(line => line.includes(__filename)) })); process.exitCode = 1; });
