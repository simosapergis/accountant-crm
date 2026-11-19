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
    checks.push(await runCheck('AT-54 client list loads, filters, clears and selects with keyboard without exposing unauthenticated data', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true });
      const denied = await context.request.get(`${baseUrl}/ajax/search_clients.php?all=1`, { maxRedirects: 0 });
      assert.equal(denied.status(), 302);
      assert.equal((await denied.text()).includes('W01 Fixture Client'), false);
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-user');
      await page.locator('input[name="password"]').fill('W01-Fictional-Password-2026');
      await Promise.all([page.waitForNavigation(), page.locator('button').click()]);
      await page.goto(`${baseUrl}/task_form.php`);
      const response = page.waitForResponse(r => r.url().endsWith('/ajax/search_clients.php?all=1'));
      await page.locator('#client_list_button').click();
      const clients = await (await response).json();
      assert.ok(clients.length >= 2, 'fictional clients must be present');
      await page.waitForFunction(count => document.querySelectorAll('#client_results .client-item').length === count, clients.length);
      assert.equal(await page.locator('#client_results img').count(), 0);
      assert.equal(await page.evaluate(() => window.crmXss), undefined);
      const scrollStyle = await page.locator('#client_results').evaluate(el => ({ overflow: getComputedStyle(el).overflowY, maxHeight: getComputedStyle(el).maxHeight }));
      assert.match(scrollStyle.overflow, /auto|scroll/);
      assert.notEqual(scrollStyle.maxHeight, 'none');
      for (const query of ['W01 Fixture Client', ...clients.filter(c => c.afm).slice(0, 1).map(c => c.afm)]) {
        await page.locator('#client_search').fill(query);
        const matches = clients.filter(c => `${c.name} ${c.afm || ''}`.toLocaleLowerCase().includes(query.toLocaleLowerCase()));
        assert.equal(await page.locator('#client_results .client-item').count(), matches.length);
      }
      await page.locator('#client_search').fill('');
      assert.equal(await page.locator('#client_results .client-item').count(), clients.length);
      await page.locator('#client_search').fill('W01 Fixture Client');
      const option = page.locator('#client_results .client-item').filter({ hasText: 'W01 Fixture Client' });
      await option.focus();
      await page.keyboard.press('Enter');
      assert.equal(await page.locator('#client_search').inputValue(), 'W01 Fixture Client');
      assert.equal(await page.locator('#client_id').inputValue(), '910001');
      assert.equal(await page.locator('#client_results').isVisible(), false);
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
    checks.push(await runCheck('admin catalogs use readable cards on small screens', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-admin');
      await page.locator('input[name="password"]').fill('W01-Fictional-Admin-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;

      for (const [route, heading] of [
        ['payment_methods.php', 'Μέσα είσπραξης'],
        ['task_titles.php', 'Τίτλοι εργασιών'],
      ]) {
        const response = await page.goto(`${baseUrl}/${route}`);
        assert.equal(response.status(), 200, `${route} should be accessible to an admin`);
        assert.equal(await page.locator('h2').innerText(), heading);
        for (const width of [390, 320]) {
          await page.setViewportSize({ width, height: 844 });
          assert.equal(await page.locator('.catalog-table-wrapper').isVisible(), false, `${route} desktop table should be hidden at ${width}px`);
          assert.equal(await page.locator('.catalog-mobile-list').isVisible(), true, `${route} cards should be visible at ${width}px`);
          const cards = page.locator('.catalog-mobile-card');
          if (await cards.count()) {
            assert.ok(await cards.first().locator('.catalog-mobile-card-name').isVisible());
            assert.ok(await cards.first().getByRole('link', { name: 'Επεξεργασία' }).isVisible());
          } else {
            assert.ok(await page.locator('.catalog-mobile-empty').isVisible());
          }
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
        }
      }
      await page.setViewportSize({ width: 1024, height: 768 });
      assert.equal(await page.locator('.catalog-table-wrapper').isVisible(), true);
      assert.equal(await page.locator('.catalog-mobile-list').isVisible(), false);
      await context.close();
    }));
    checks.push(await runCheck('recurring tasks use cards on small screens and a table on desktop', async () => {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-user');
      await page.locator('input[name="password"]').fill('W01-Fictional-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;

      await page.goto(`${baseUrl}/recurring_tasks.php`);
      assert.equal(await page.locator('.recurring-mobile-card').count() > 0, true, 'test database should contain the W01 mobile recurrence fixture');
      assert.ok(await page.locator('.recurring-mobile-list').getByText('W01 Mobile Recurring Filing', { exact: true }).isVisible());
      for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        assert.equal(await page.locator('.recurring-table-wrapper').isVisible(), false);
        assert.equal(await page.locator('.recurring-mobile-list').isVisible(), true);
        assert.equal(await page.locator('.recurring-mobile-card').first().getByRole('link', { name: 'Ρυθμίσεις' }).isVisible(), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
      }

      await page.setViewportSize({ width: 1024, height: 768 });
      assert.equal(await page.locator('.recurring-table-wrapper').isVisible(), true);
      assert.equal(await page.locator('.recurring-mobile-list').isVisible(), false);
      assert.ok(await page.locator('.recurring-table').getByText('W01 Mobile Recurring Filing', { exact: true }).isVisible());
      await context.close();
    }));
    checks.push(await runCheck('AT-49 forms, catalogs, task cards, and recurrence settings fit the requested viewport matrix and keyboard focus remains visible', async () => {
      const widths = [320, 375, 390, 768, 1024, 1280, 1440];
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 320, height: 844 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-admin');
      await page.locator('input[name="password"]').fill('W01-Fictional-Admin-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;

      await page.goto(`${baseUrl}/recurring_tasks.php`);
      const recurrenceSettingsHref = await page.locator('.recurring-mobile-card a').first().getAttribute('href');
      assert.ok(recurrenceSettingsHref, 'the recurring fixture should expose its settings action');
      const routes = [
        { path: 'tasks.php', selector: '.mobile-cards', mobile: true },
        { path: 'task_form.php?id=910001', selector: '#title_selection' },
        { path: 'payment_methods.php', selector: '.catalog-mobile-list', mobile: true },
        { path: 'payment_method_form.php', selector: '#catalog-name' },
        { path: 'task_titles.php', selector: '.catalog-mobile-list', mobile: true },
        { path: 'task_title_form.php', selector: '#catalog-name' },
        { path: 'recurring_tasks.php', selector: '.recurring-mobile-list', mobile: true },
        { path: recurrenceSettingsHref, selector: 'input[name="day_of_month"]' },
      ];

      for (const width of widths) {
        await page.setViewportSize({ width, height: 844 });
        for (const route of routes) {
          const response = await page.goto(`${baseUrl}/${route.path}`);
          assert.equal(response.status(), 200, `${route.path} should load at ${width}px`);
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, `${route.path} overflows at ${width}px`);

          if (route.mobile !== undefined) {
            const shouldShowMobile = width <= 768;
            assert.equal(await page.locator(route.selector).isVisible(), shouldShowMobile, `${route.selector} breakpoint mismatch at ${width}px`);
            if (route.path === 'tasks.php') {
              assert.equal(await page.locator('.desktop-table').isVisible(), !shouldShowMobile);
            } else if (route.path === 'recurring_tasks.php') {
              assert.equal(await page.locator('.recurring-table-wrapper').isVisible(), !shouldShowMobile);
            } else {
              assert.equal(await page.locator('.catalog-table-wrapper').isVisible(), !shouldShowMobile);
            }
          } else {
            assert.ok(await page.locator(route.selector).isVisible(), `${route.selector} should be visible at ${width}px`);
          }

          if (width === 320) {
            await page.keyboard.press('Tab');
            const focusIsVisible = await page.evaluate(() => {
              const node = document.activeElement;
              const rect = node.getBoundingClientRect();
              const style = getComputedStyle(node);
              return node !== document.body && style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < window.innerWidth;
            });
            assert.equal(focusIsVisible, true, `${route.path} should keep keyboard focus visible at 320px`);
          }
        }
      }
      await context.close();
    }));
    checks.push(await runCheck('AT-49 keyboard traversal reaches visible controls with a focus indicator and finds no clipped visible text at every requested width', async () => {
      const widths = [320, 375, 390, 768, 1024, 1280, 1440];
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 320, height: 844 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/login.php`);
      await page.locator('input[name="username"]').fill('w01-fake-admin');
      await page.locator('input[name="password"]').fill('W01-Fictional-Admin-Password-2026');
      const navigation = page.waitForNavigation();
      await page.locator('button').click();
      await navigation;

      await page.goto(`${baseUrl}/recurring_tasks.php`);
      const recurrenceSettingsHref = await page.locator('.recurring-mobile-card a').first().getAttribute('href');
      assert.ok(recurrenceSettingsHref, 'the recurring fixture should expose its settings action');
      const routes = [
        'tasks.php',
        'task_form.php?id=910001',
        'payment_methods.php',
        'payment_method_form.php',
        'task_titles.php',
        'task_title_form.php',
        'recurring_tasks.php',
        recurrenceSettingsHref,
      ];
      const focusableSelector = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

      for (const width of widths) {
        await page.setViewportSize({ width, height: 844 });
        for (const route of routes) {
          const response = await page.goto(`${baseUrl}/${route}`);
          assert.equal(response.status(), 200, `${route} should load at ${width}px`);
          await page.bringToFront();
          await page.evaluate(() => window.scrollTo(0, 0));
          const visibleCount = await page.locator(focusableSelector).evaluateAll((nodes) => nodes.filter((node) => {
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
          }).length);
          assert.ok(visibleCount > 0, `${route} should expose keyboard controls at ${width}px`);

          const reachedControls = new Set();
          for (let step = 0; step < visibleCount * 3 && reachedControls.size < visibleCount; step += 1) {
            await page.keyboard.press('Tab');
            await page.evaluate(() => {
              const node = document.activeElement;
              const rect = node.getBoundingClientRect();
              if (rect.bottom <= 0 || rect.top >= window.innerHeight || rect.right <= 0 || rect.left >= window.innerWidth) {
                const maximumScroll = document.documentElement.scrollHeight - window.innerHeight;
                const targetScroll = Math.min(maximumScroll, Math.max(0, window.scrollY + rect.top - 40));
                window.scrollTo(0, targetScroll);
              }
            });
            await page.waitForFunction(() => {
              const rect = document.activeElement.getBoundingClientRect();
              return rect.bottom > 0 && rect.top < window.innerHeight && rect.right > 0 && rect.left < window.innerWidth;
            });
            const state = await page.evaluate(() => {
              const node = document.activeElement;
              const rect = node.getBoundingClientRect();
              const style = getComputedStyle(node);
              const focusables = [...document.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
              return {
                tag: node.tagName,
                text: (node.innerText || node.getAttribute('aria-label') || node.getAttribute('name') || '').trim().slice(0, 80),
                index: focusables.indexOf(node),
                focused: node.matches(':focus'),
                rect: { top: rect.top, right: rect.right, bottom: rect.bottom, left: rect.left, width: rect.width, height: rect.height },
                scroll: { x: window.scrollX, y: window.scrollY },
                visible: node !== document.body && style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < window.innerWidth && rect.bottom > 0 && rect.top < window.innerHeight,
                focusIndicator: style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0 || style.boxShadow !== 'none',
                outline: `${style.outlineStyle} ${style.outlineWidth} ${style.outlineColor}`,
                focusVisible: node.matches(':focus-visible'),
              };
            });
            if (!state.focused) continue;
            reachedControls.add(state.index);
            assert.equal(state.visible, true, `${route} at ${width}px has offscreen keyboard focus on ${state.tag} ${state.text}; rect=${JSON.stringify(state.rect)} scroll=${JSON.stringify(state.scroll)}`);
            assert.equal(state.focusIndicator, true, `${route} at ${width}px has no visible keyboard focus indicator at step ${step} on ${state.tag} ${state.text}; outline=${state.outline}; :focus-visible=${state.focusVisible}`);
          }
          assert.equal(reachedControls.size, visibleCount, `${route} at ${width}px reached ${reachedControls.size} of ${visibleCount} visible focusable controls by keyboard`);

          const clipped = await page.evaluate(() => [...document.querySelectorAll('body *')].filter((node) => {
            if (!node.textContent.trim() || ['SCRIPT', 'STYLE', 'SVG', 'PATH', 'INPUT', 'TEXTAREA', 'SELECT', 'OPTION'].includes(node.tagName)) return false;
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            const visible = style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            const clipsX = ['hidden', 'clip'].includes(style.overflowX) && node.scrollWidth > node.clientWidth + 1;
            const clipsY = ['hidden', 'clip'].includes(style.overflowY) && node.scrollHeight > node.clientHeight + 1;
            return visible && (clipsX || clipsY);
          }).map((node) => ({ tag: node.tagName, className: String(node.className).slice(0, 80), text: node.textContent.trim().replace(/\\s+/g, ' ').slice(0, 80) })).slice(0, 8));
          assert.deepEqual(clipped, [], `${route} at ${width}px clips visible text: ${JSON.stringify(clipped)}`);
        }
      }
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
