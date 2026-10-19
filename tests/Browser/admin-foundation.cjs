const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(path.join(process.env.TEMP, 'rsvp-browser-tools/node_modules/playwright'));
const fixture = path.join(process.env.TEMP, 'rsvp-blade-phase1-browser/credentials.json');
const credentials = JSON.parse(fs.readFileSync(fixture, 'utf8'));
const output = path.resolve(__dirname, '../../MDFiles/phase1-qa');
fs.mkdirSync(output, { recursive: true });
const origin = 'http://127.0.0.1:8012';
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
 const checks = [];
 const errors = [];
 const context = await browser.newContext({ reducedMotion: 'reduce' });
 const page = await context.newPage();
 page.on('pageerror', error => errors.push(error.message));
 try {
  await page.goto(origin + '/admin');
  assert.match(page.url(), /\/admin\/login$/);
  for (const width of [320, 375, 390, 768, 1024, 1440]) {
   await page.setViewportSize({ width, height: 950 });
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(output, 'login-1440.png'), fullPage: true });
  await page.getByLabel('Username or email').fill(credentials.identifier);
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(origin + '/admin');
  checks.push('real HTTP Blade login and guest redirect');
  assert.equal(await page.locator('nav a[title="Users"]').count(), 1);
  const apiUser = await page.evaluate(async () => (await fetch('/api/auth/user', { headers: { Accept: 'application/json' } })).status);
  assert.equal(apiUser, 200);
  checks.push('Blade session is accepted by existing Sanctum API');
  const forged = await context.request.post(origin + '/admin/logout', { headers: { Accept: 'application/json', Origin: 'http://untrusted.example.test' } });
  assert.equal(forged.status(), 419);
  checks.push('real HTTP forged logout rejected with 419');
  for (const width of [320, 375, 390, 768, 1024, 1440]) {
   await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
   if (width < 1024) {
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await page.getByRole('dialog').waitFor({ state: 'visible' });
    assert.equal(await page.locator('.admin-workspace').evaluate(el => el.inert), true);
    await page.locator('nav a[title="Users"]').focus();
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.body.hasAttribute('data-sidebar-open'));
    assert.equal(await page.getByRole('button', { name: 'Open navigation' }).evaluate(el => el === document.activeElement), true);
   } else {
    assert.equal(await page.getByRole('button', { name: 'Open navigation' }).isVisible(), false);
    await page.getByRole('button', { name: 'Collapse sidebar' }).click();
    assert.equal(await page.locator('#admin-sidebar').evaluate(el => el.getBoundingClientRect().width), 88);
    await page.getByRole('button', { name: 'Expand sidebar' }).click();
    assert.equal(await page.locator('#admin-sidebar').evaluate(el => el.getBoundingClientRect().width), 256);
   }
   checks.push(`${width}px: no overflow; responsive navigation and focus`);
   if ([390,1440].includes(width)) await page.screenshot({ path: path.join(output, 'shell-'+width+'.png'), fullPage: true });
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole('button', { name: 'Open navigation' }).click();
  const focusable = page.locator('#admin-sidebar a, #admin-sidebar button');
  const last = focusable.last();
  await last.focus(); await page.keyboard.press('Tab');
  assert.equal(await page.locator('#admin-sidebar a').first().evaluate(el => el === document.activeElement), true);
  await page.keyboard.press('Shift+Tab');
  assert.equal(await last.evaluate(el => el === document.activeElement), true);
  await page.keyboard.press('Escape');
  checks.push('mobile drawer wraps Tab/Shift+Tab and restores focus');
  await page.goto(origin + '/admin/users');
  await page.getByRole('heading', { name: 'Users', exact: true }).waitFor();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.locator('#admin-sidebar button[title="Sign out"]').click();
  await page.waitForURL(origin + '/admin/login');
  await page.getByLabel('Username or email').fill('blade-qa-staff');
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(origin + '/admin');
  assert.equal(await page.locator('nav a[title="Users"]').count(), 0);
  assert.equal((await page.goto(origin + '/admin/users')).status(), 403);
  checks.push('staff Users link hidden and direct access forbidden');
  assert.deepEqual(errors, []);
  checks.push('no browser JavaScript errors');
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ environment: 'disposable SQLite, local Laravel, Edge; no production data', checks }, null, 2));
  console.log(JSON.stringify({ passed: checks.length, checks }));
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
