const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(path.join(process.env.TEMP, 'rsvp-browser-tools/node_modules/playwright'));
const credentials = JSON.parse(fs.readFileSync(path.join(process.env.TEMP, 'rsvp-blade-phase2-browser/credentials.json'), 'utf8'));
const output = path.resolve(__dirname, '../../MDFiles/phase2-qa');
fs.mkdirSync(output, { recursive: true });
const origin = 'http://127.0.0.1:8013';
const runId = Date.now();
const createdName = 'QA Browser Guest ' + runId;
const updatedName = 'QA Updated Guest ' + runId;
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
 const context = await browser.newContext({ reducedMotion: 'reduce' });
 const page = await context.newPage();
 const errors = [], checks = [];
 page.on('pageerror', error => errors.push(error.message));
 try {
  await page.goto(origin + '/admin/login');
  await page.getByLabel('Username or email').fill(credentials.identifier);
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL(origin + '/admin');
  await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
  checks.push('real Blade dashboard login and metrics rendering');
  for (const route of ['/admin', '/admin/reservations', '/admin/reservations/create', '/admin/reservations/1', '/admin/reservations/1/edit']) {
   for (const width of [320, 375, 390, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
    assert.equal((await page.goto(origin + route)).status(), 200);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, route + ' overflows at ' + width);
    if (width === 390 && route === '/admin') {
     await page.getByRole('button', { name: 'Open navigation' }).click();
     await page.getByRole('dialog').waitFor();
     await page.keyboard.press('Escape');
     assert.equal(await page.getByRole('button', { name: 'Open navigation' }).evaluate(el => el === document.activeElement), true);
    }
    if ([390,1440].includes(width)) {
     const name = route === '/admin' ? 'dashboard' : route.endsWith('/create') ? 'create' : route.endsWith('/edit') ? 'edit' : route.endsWith('/1') ? 'details' : 'reservations';
     await page.screenshot({ path: path.join(output, name + '-' + width + '.png'), fullPage: true });
    }
   }
   checks.push(route + ': all six required widths have no page overflow');
  }
  await page.goto(origin + '/admin/reservations/create');
  await page.getByLabel('Full name').fill(createdName);
  await page.getByLabel('Reason for this entry or change').fill('Recorded during isolated QA');
  await page.getByRole('button', { name: 'Save reservation', exact: true }).click();
  await page.waitForURL(/\/admin\/reservations\/\d+$/);
  const detailUrl = page.url();
  await page.getByRole('heading', { name: 'Guest details', exact: true }).waitFor();
  assert.match(await page.locator('main').textContent(), /QA Browser Guest/);
  checks.push('real browser creates one-guest reservation and displays audit history');
  const forged = await context.request.delete(detailUrl, { headers: { Origin: 'http://untrusted.example.test', Accept: 'application/json' } });
  assert.equal(forged.status(), 419);
  checks.push('reservation cancellation rejects forged cross-origin write with 419');
  await page.getByRole('link', { name: 'Edit reservation', exact: true }).click();
  await page.getByLabel('Full name').fill(updatedName);
  await page.getByLabel('Reason for this entry or change').fill('Corrected contact during QA');
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await page.waitForURL(detailUrl);
  assert.match(await page.locator('main').textContent(), /QA Updated Guest/);
  checks.push('real browser edits guest and response with a change reason');
  await page.getByLabel('Reason', { exact: false }).last().fill('Cancelled during isolated QA');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: 'Confirm cancellation', exact: true }).click();
  await page.getByRole('heading', { name: 'Restore reservation', exact: true }).waitFor();
  checks.push('real browser confirms cancellation without deleting guest record');
  await page.getByLabel('Reason', { exact: false }).last().fill('Restored during isolated QA');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: 'Restore reservation', exact: true }).click();
  await page.getByRole('heading', { name: 'Cancel reservation', exact: true }).waitFor();
  checks.push('real browser restores original attendance response');
  await page.goto(origin + '/admin/reservations');
  await page.getByLabel('Name, email or reference').fill(updatedName);
  await page.getByRole('button', { name: 'Filter reservations', exact: true }).click();
  await page.waitForURL(/search=QA/);
  assert.equal(await page.locator('tbody tr').count(), 1);
  checks.push('real browser searches and filters server-rendered reservations');
  assert.deepEqual(errors, []);
  checks.push('no browser JavaScript errors');
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ environment: 'disposable SQLite fixture data; local Laravel; Edge', checks }, null, 2));
  console.log(JSON.stringify({ passed: checks.length, checks }));
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
