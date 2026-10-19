const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(path.join(process.env.TEMP, 'rsvp-browser-tools/node_modules/playwright'));
const credentials = JSON.parse(fs.readFileSync(path.join(process.env.TEMP, 'rsvp-blade-phase3-browser/credentials.json'), 'utf8'));
const origin = 'http://127.0.0.1:8014';
const output = path.resolve(__dirname, '../../MDFiles/phase3-qa');
fs.mkdirSync(output, { recursive: true });
let stage = 'startup';
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
 const context = await browser.newContext({ reducedMotion: 'reduce' });
 const staffContext = await browser.newContext({ reducedMotion: 'reduce' });
 const page = await context.newPage(); const staff = await staffContext.newPage();
 const errors = [], checks = [];
 page.on('pageerror', error => errors.push(error.name)); staff.on('pageerror', error => errors.push(error.name));
 async function login(target, identifier, password) {
  await target.goto(origin + '/admin/login');
  await target.getByLabel('Username or email').fill(identifier);
  await target.getByLabel('Password', { exact: true }).fill(password);
  await target.getByRole('button', { name: 'Sign in', exact: true }).click();
  await target.waitForURL(origin + '/admin');
 }
 try {
  stage = 'login'; await login(page, credentials.identifier, credentials.password);
  const routes = ['/admin/users', '/admin/users/create', '/admin/users/'+credentials.staff_id, '/admin/users/'+credentials.staff_id+'/edit', '/admin/users/'+credentials.staff_id+'/password', '/admin/guests', '/admin/guests/'+credentials.guest_id, '/admin/guests/'+credentials.guest_id+'/edit'];
  stage = 'responsive screens';
  for (const route of routes) {
   for (const width of [320, 375, 390, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
    assert.equal((await page.goto(origin + route)).status(), 200);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if ([390,1440].includes(width)) {
     const relative = route.replace('/admin/', '').replaceAll('/', '-');
     await page.screenshot({ path: path.join(output, relative+'-'+width+'.png'), fullPage: true });
    }
   }
   checks.push(route + ': six required widths, no page overflow');
  }
  stage = 'create user'; const runId = Date.now(); const email = 'qa-new-'+runId+'@example.test';
  await page.goto(origin + '/admin/users/create');
  await page.getByLabel('Display name').fill('QA New Organizer');
  await page.getByLabel('Email', { exact: false }).fill(email);
  await page.getByLabel('Username (optional)', { exact: true }).fill('qa-new-'+runId);
  await page.getByLabel('New account password', { exact: false }).first().fill(credentials.new_password);
  await page.getByLabel('Confirm new account password').fill(credentials.new_password);
  await page.getByLabel('Reason', { exact: false }).fill('Created during isolated QA');
  await page.getByLabel('Your current password').fill(credentials.password);
  await page.getByRole('button', { name: 'Create user', exact: true }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/); const userUrl = page.url();
  checks.push('real browser creates hashed-password staff account');
  stage = 'edit user'; await page.getByRole('link', { name: 'Edit user', exact: true }).click();
  await page.getByLabel('Display name').fill('QA Updated Organizer');
  await page.getByLabel('Reason', { exact: false }).fill('Updated during isolated QA');
  await page.getByLabel('Your current password').fill(credentials.password);
  await page.getByRole('button', { name: 'Save user', exact: true }).click();
  await page.waitForURL(userUrl); await page.getByRole('heading', { name: 'QA Updated Organizer', exact: true }).waitFor();
  checks.push('real browser edits account details');
  stage = 'forged deactivation';
  const forged = await context.request.delete(userUrl, { headers: { Origin: 'http://untrusted.example.test', Accept: 'application/json' } });
  assert.equal(forged.status(), 419); checks.push('forged account deactivation rejected with 419');
  stage = 'password change'; await page.getByRole('link', { name: 'Change password', exact: true }).click();
  await page.getByLabel('New account password', { exact: false }).first().fill(credentials.new_password+'X');
  await page.getByLabel('Confirm new account password').fill(credentials.new_password+'X');
  await page.getByLabel('Your current password').fill(credentials.password);
  await page.getByLabel('Reason', { exact: false }).fill('Password changed during isolated QA');
  await page.getByRole('button', { name: 'Change password', exact: true }).click();
  await page.waitForURL(userUrl); checks.push('real browser changes target password after actor confirmation');
  stage = 'deactivation';
  await page.getByLabel('Reason', { exact: false }).fill('Access removed during isolated QA');
  await page.getByLabel('Your current password').fill(credentials.password);
  await page.getByRole('checkbox').check(); await page.getByRole('button', { name: 'Deactivate user', exact: true }).click();
  await page.getByRole('heading', { name: 'This account is inactive', exact: true }).waitFor();
  checks.push('real browser deactivates account without deletion');
  stage = 'reactivation'; await page.getByRole('link', { name: 'Edit user', exact: true }).click();
  await page.getByLabel('Account status').selectOption('1');
  await page.getByLabel('Reason', { exact: false }).fill('Access restored during isolated QA');
  await page.getByLabel('Your current password').fill(credentials.password);
  await page.getByRole('button', { name: 'Save user', exact: true }).click();
  await page.waitForURL(userUrl); assert.equal(await page.getByRole('heading', { name: 'This account is inactive', exact: true }).count(), 0);
  checks.push('real browser reactivates retained account');
  stage = 'staff access'; await login(staff, credentials.staff_identifier, credentials.password);
  assert.equal((await staff.goto(origin+'/admin/users')).status(), 403);
  await staff.goto(origin+'/admin/guests'); assert.equal(await staff.locator('nav a[title="Users"]').count(), 0);
  checks.push('staff cannot access user management and Users navigation is hidden');
  stage = 'guest update'; await staff.goto(origin+'/admin/guests/'+credentials.guest_id+'/edit');
  await staff.getByLabel('Full name').fill('QA Updated Guest Contact');
  await staff.getByLabel('Reason', { exact: false }).fill('Contact corrected during isolated QA');
  await staff.getByRole('button', { name: 'Save contact', exact: true }).click();
  await staff.waitForURL(origin+'/admin/guests/'+credentials.guest_id);
  await staff.getByRole('heading', { name: 'QA Updated Guest Contact', exact: true }).waitFor();
  assert.equal(await staff.locator('tbody tr').count(), 1);
  checks.push('staff edits existing own-event contact and linked reservation remains');
  assert.deepEqual(errors, []); checks.push('no browser JavaScript errors');
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ environment: 'disposable local SQLite fixtures; Edge; no real account edits', checks }, null, 2));
  console.log(JSON.stringify({ passed: checks.length, checks }));
 } finally { await browser.close(); }
})().catch(error => { console.error(JSON.stringify({ stage, failure: error.name, location: error.stack?.split('\n').find(line => line.includes(__filename)) })); process.exitCode = 1; });
