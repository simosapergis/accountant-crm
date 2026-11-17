const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');

const baseUrl = process.env.CRM_BASE_URL || 'https://127.0.0.1:18443/crm';
const chromePath = process.env.CHROME_BIN || '/usr/bin/google-chrome';

async function runCheck(name, check) {
  try {
    await check();
    console.log(`PASS ${name}`);
    return true;
  } catch (error) {
    console.error(`FAIL ${name}: ${error.message}`);
    return false;
  }
}

(async () => {
  const browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
    args: ['--no-sandbox'],
  });
  try {
    const checks = [];
    checks.push(await runCheck('login page loads over HTTPS under /crm/', async () => {
      const page = await browser.newPage({ ignoreHTTPSErrors: true });
      const response = await page.goto(`${baseUrl}/login.php`);

      assert.equal(response.status(), 200);
      assert.equal(await page.locator('input[name="username"]').isVisible(), true);
      assert.equal(await page.locator('input[name="password"]').isVisible(), true);
    }));
    checks.push(await runCheck('successful login rotates the session ID and opens the protected dashboard', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      const before = (await context.cookies()).find((cookie) => cookie.name === 'PHPSESSID');
      await page.locator('input[name="username"]').fill('w01-fake-user');
      await page.locator('input[name="password"]').fill('W01-Fictional-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      const response = await navigation;
      const after = (await context.cookies()).find((cookie) => cookie.name === 'PHPSESSID');

      assert.equal(response.status(), 200);
      assert.equal(page.url(), `${baseUrl}/index.php`);
      assert.ok(before && after && before.value !== after.value, 'session ID should rotate after login');
      assert.equal(await page.locator('body').innerText().then((text) => text.includes('Notice: session_start')), false);
      await context.close();
    }));
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await page.goto(`${baseUrl}/login.php`);
    const session = (await context.cookies()).find((cookie) => cookie.name === 'PHPSESSID');
    assert.ok(session, 'login page should create a PHP session cookie over HTTPS');

    checks.push(await runCheck('session cookie has Secure', async () => {
      assert.equal(session.secure, true);
    }));
    checks.push(await runCheck('session cookie has HttpOnly', async () => {
      assert.equal(session.httpOnly, true);
    }));
    checks.push(await runCheck('session cookie has SameSite=Lax', async () => {
      assert.equal(session.sameSite, 'Lax');
    }));
    checks.push(await runCheck('session cookie is scoped to /crm/', async () => {
      assert.equal(session.path, '/crm/');
    }));

    await context.close();
    if (checks.includes(false)) {
      process.exitCode = 1;
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
