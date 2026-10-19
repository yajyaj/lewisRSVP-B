const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(path.join(process.env.TEMP, 'rsvp-browser-tools/node_modules/playwright'));
const credentials = JSON.parse(fs.readFileSync(path.join(process.env.TEMP, 'rsvp-blade-phase4-browser/credentials.json'), 'utf8'));
const origin = 'http://127.0.0.1:8015';
const output = path.resolve(__dirname, '../../MDFiles/phase4-qa');
fs.mkdirSync(output, { recursive: true });
let stage = 'startup';
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
 const context = await browser.newContext({ reducedMotion: 'reduce' });
 const page = await context.newPage();
 page.setDefaultTimeout(15000);
 page.setDefaultNavigationTimeout(20000);
 const errors = [], checks = [];
 page.on('pageerror', error => errors.push(error.name));
 async function login(password) {
  await page.goto(origin + '/admin/login');
  await page.getByLabel('Username or email').fill('qa-settings');
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(origin + '/admin');
 }
 try {
  stage = 'login'; await login(credentials.password);
  stage = 'create event'; await page.goto(origin + '/admin/event-settings/create');
  await page.getByLabel('Internal event name', { exact: false }).fill('QA Celebration');
  await page.getByLabel('Invitation title', { exact: false }).fill('QA Underwater Celebration');
  await page.locator('#occasion-0-label').fill('Christening');
  assert.equal(await page.locator('#second-occasion').isVisible(), false);
  await page.getByRole('button', { name: 'Add occasion', exact: true }).click();
  await page.locator('#occasion-1-label').fill('1st Birthday');
  await page.locator('#occasion-1-venue_name').fill('QA Birthday Venue');
  await page.locator('#occasion-1-start_time').fill('11:30');
  await page.locator('#occasion-1-end_time').fill('15:00');
  await page.getByLabel('Event date', { exact: false }).fill('2027-01-09');
  await page.locator('#occasion-0-start_time').fill('10:00');
  await page.locator('#occasion-0-end_time').fill('11:00');
  await page.locator('#occasion-0-venue_name').fill('QA Celebration Venue');
  await page.getByLabel('RSVP deadline', { exact: false }).fill('2027-01-08T18:30');
  assert.deepEqual(await page.locator('form :invalid').evaluateAll(fields => fields.map(field => ({ name: field.name, validation: field.validationMessage }))), []);
  await page.getByRole('button', { name: 'Create event', exact: true }).click();
  await page.waitForURL(/\/admin\/event-settings\/\d+\/edit$/);
  const editUrl = page.url();
  const slug = new URL(await page.locator('a[target="_blank"]').first().getAttribute('href')).pathname.slice(1);
  let response = await context.request.get(origin + '/api/events/' + slug);
  assert.equal(response.status(), 200);
  let event = (await response.json()).event;
  assert.equal(event.venue_name, 'QA Celebration Venue');
  assert.equal(event.event_stops[1].venue_name, 'QA Birthday Venue');
  assert.equal(event.event_stops[1].label, '1st Birthday');
  assert.equal(event.rsvp_deadline, '2027-01-08T10:30:00+00:00');
  checks.push('browser creates published event; public API uses saved venue and UTC deadline');
  stage = 'responsive screens';
  for (const route of ['/admin/event-settings', '/admin/event-settings/create', new URL(editUrl).pathname, '/admin/profile']) {
   for (const width of [320,375,390,768,1024,1440]) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
    assert.equal((await page.goto(origin + route)).status(), 200);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    assert.equal(await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(el => el.id); return new Set(ids).size === ids.length; }), true);
    if ([390,1440].includes(width)) {
     await page.screenshot({ path: path.join(output, route.replace('/admin/','').replaceAll('/','-')+'-'+width+'.png'), fullPage: true });
    }
   }
   checks.push(route + ': six required widths, no overflow or duplicate element IDs');
  }
  stage = 'edit event'; await page.goto(editUrl);
  await page.locator('#occasion-0-venue_name').fill('QA Updated Venue');
  await page.getByRole('button', { name: 'Save event settings', exact: true }).click();
  await page.waitForURL(editUrl);
  await page.getByText('Event settings saved. Published invitations use these details.', { exact: true }).waitFor();
  event = (await (await context.request.get(origin+'/api/events/'+slug)).json()).event;
  assert.equal(event.venue_name, 'QA Updated Venue');
  checks.push('browser saves event edits and public API reflects update');
  stage = 'CSRF';
  assert.equal((await context.request.put(origin+'/admin/profile', { headers: { Origin: 'http://untrusted.example.test', Accept: 'application/json' }, data: { name: 'Forged' } })).status(), 419);
  checks.push('forged profile write rejected with 419');
  stage = 'update profile'; await page.goto(origin+'/admin/profile');
  await page.getByLabel('Display name', { exact: false }).fill('QA Updated Organizer');
  await page.getByLabel('Email', { exact: false }).fill('qa-settings-updated@example.test');
  await page.locator('#profile-current-password').fill(credentials.password);
  await page.getByRole('button', { name: 'Save profile', exact: true }).click();
  await page.waitForURL(origin+'/admin/profile');
  await page.getByText('Your profile was updated.', { exact: true }).waitFor();
  assert.equal(await page.getByLabel('Display name', { exact: false }).inputValue(), 'QA Updated Organizer');
  checks.push('browser updates own name and email with current-password verification');
  stage = 'change own password';
  await page.locator('#password').fill(credentials.new_password);
  await page.getByLabel('Confirm new password', { exact: false }).fill(credentials.new_password);
  await page.locator('#password-current-password').fill(credentials.password);
  await page.getByRole('button', { name: 'Change my password', exact: true }).click();
  await page.waitForURL(origin+'/admin/login');
  assert.equal((await page.goto(origin+'/admin/profile')).url().endsWith('/admin/login'), true);
  await login(credentials.new_password);
  checks.push('own password change signs out current session; new password signs in');
  assert.deepEqual(errors, []); checks.push('no browser JavaScript errors');
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ environment: 'disposable local SQLite fixtures; Edge; no real account edits', checks }, null, 2));
  console.log(JSON.stringify({ passed: checks.length, checks }));
 } finally { await browser.close(); }
})().catch(error => { console.error(JSON.stringify({ stage, failure: error.name, location: error.stack?.split('\n').find(line => line.includes(__filename)) })); process.exitCode = 1; });
