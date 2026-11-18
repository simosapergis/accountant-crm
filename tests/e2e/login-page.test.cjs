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
    checks.push(await runCheck('login rotates the session ID, logout ends it, and protected pages reject the old session', async () => {
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

      const compactNavigation = await page.locator('.hamburger').isVisible();
      if (compactNavigation) {
        await page.locator('.hamburger').click();
      }
      const logoutNavigation = page.waitForNavigation();
      const logoutButton = compactNavigation
        ? page.locator('.mobile-menu form[action$="/logout.php"] button[type="submit"]')
        : page.locator('.menu-user form[action$="/logout.php"] button[type="submit"]');
      await logoutButton.click();
      const logoutResponse = await logoutNavigation;
      assert.equal(logoutResponse.status(), 200);
      assert.equal(page.url(), `${baseUrl}/login.php`);
      await page.goto(`${baseUrl}/tasks.php`);
      assert.equal(page.url(), `${baseUrl}/login.php`, 'logout should make protected pages unavailable');
      await context.close();
    }));
    checks.push(await runCheck('client autocomplete renders hostile names as text', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-user');
      await page.locator('input[name="password"]').fill('W01-Fictional-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;
      await page.goto(`${baseUrl}/task_form.php`);
      await page.locator('#client_search').fill('W02 XSS');
      await page.locator('#client_results .client-item').waitFor({ state: 'visible' });

      assert.equal(await page.locator('#client_results img').count(), 0);
      assert.equal(await page.evaluate(() => window.crmXss), undefined);
      await context.close();
    }));
    checks.push(await runCheck('navigation fits desktop and keeps account controls available at compact widths', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 800 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-user');
      await page.locator('input[name="password"]').fill('W01-Fictional-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;

      assert.equal(await page.locator('.hamburger').isVisible(), true);
      assert.equal(await page.locator('.menu-user').isVisible(), false);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
      await page.locator('.hamburger').click();
      assert.equal(await page.locator('.mobile-user-box').isVisible(), true);
      assert.match(await page.locator('.mobile-user-box').innerText(), /W01 Fictional User/);
      const compactWidths = await page.evaluate(() => {
        const header = document.querySelector('header').getBoundingClientRect();
        const drawer = document.querySelector('.mobile-menu').getBoundingClientRect();
        return { headerLeft: header.left, headerRight: header.right, drawerLeft: drawer.left, drawerRight: drawer.right };
      });
      assert.ok(Math.abs(compactWidths.headerLeft - compactWidths.drawerLeft) <= 1, JSON.stringify(compactWidths));
      assert.ok(Math.abs(compactWidths.headerRight - compactWidths.drawerRight) <= 1, JSON.stringify(compactWidths));

      await page.setViewportSize({ width: 390, height: 844 });
      const mobileWidths = await page.evaluate(() => {
        const header = document.querySelector('header').getBoundingClientRect();
        const drawer = document.querySelector('.mobile-menu').getBoundingClientRect();
        return { headerLeft: header.left, headerRight: header.right, drawerLeft: drawer.left, drawerRight: drawer.right };
      });
      assert.ok(Math.abs(mobileWidths.headerLeft - mobileWidths.drawerLeft) <= 1, JSON.stringify(mobileWidths));
      assert.ok(Math.abs(mobileWidths.headerRight - mobileWidths.drawerRight) <= 1, JSON.stringify(mobileWidths));
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);

      await page.setViewportSize({ width: 1600, height: 900 });
      await page.reload();
      assert.equal(await page.locator('.menu-links').isVisible(), true);
      assert.equal(await page.locator('.menu-user').isVisible(), true);
      const desktopHeader = await page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        headerRight: document.querySelector('header').getBoundingClientRect().right,
        linksRight: document.querySelector('.menu-links').getBoundingClientRect().right,
        userRight: document.querySelector('.menu-user').getBoundingClientRect().right,
      }));
      assert.ok(desktopHeader.documentWidth <= desktopHeader.viewportWidth, JSON.stringify(desktopHeader));
      assert.ok(desktopHeader.linksRight <= desktopHeader.headerRight, JSON.stringify(desktopHeader));
      assert.ok(desktopHeader.userRight <= desktopHeader.viewportWidth, JSON.stringify(desktopHeader));
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
