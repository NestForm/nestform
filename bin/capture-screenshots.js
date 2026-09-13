/**
 * Capture Nestform admin/front screenshots (Pro UI, no admin bar).
 * Credentials via env: NF_USER, NF_PASS
 * Optional: NF_BASE, NF_OUT, NF_BUILDER_ID, NF_PRO_FORM_ID
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = process.env.NF_BASE || 'https://wordpress-custom.local';
const USER = process.env.NF_USER;
const PASS = process.env.NF_PASS;
const OUT = process.env.NF_OUT || path.join(__dirname, '..', 'assets');
const BUILDER_ID = process.env.NF_BUILDER_ID || '556';
const PRO_FORM_ID = process.env.NF_PRO_FORM_ID || '756';

if (!USER || !PASS) {
  console.error('NF_USER and NF_PASS required');
  process.exit(1);
}

const CHROME_CSS = `
  #wpadminbar { display: none !important; visibility: hidden !important; height: 0 !important; }
  html.wp-toolbar { padding-top: 0 !important; }
  html { margin-top: 0 !important; padding-top: 0 !important; }
  body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
  body.admin-bar #wpcontent,
  body.admin-bar #wpbody,
  body.admin-bar #wpbody-content { padding-top: 0 !important; margin-top: 0 !important; }
  #wpwrap, #wpcontent { margin-top: 0 !important; padding-top: 0 !important; }
  .nestform-app {
    height: 100vh !important;
    min-height: 100vh !important;
  }
  body.admin-bar .nestform-app,
  body.admin-bar.nestform-app-screen .nestform-app {
    height: 100vh !important;
    min-height: 100vh !important;
  }
  .fs-notice,
  .notice,
  .update-nag,
  #wpfooter,
  .nestform-review-request { display: none !important; }
`;

const shots = [
  {
    file: 'screenshot-1.png',
    url: `${BASE}/wp-admin/edit.php?post_type=nestform&page=nestform-dashboard`,
    wait: 2800,
    scrubPii: true,
  },
  {
    file: 'screenshot-2.png',
    url: `${BASE}/wp-admin/edit.php?post_type=nestform`,
    wait: 2200,
    scrubPii: true,
  },
  {
    file: 'screenshot-3.png',
    url: `${BASE}/wp-admin/post.php?post=${BUILDER_ID}&action=edit`,
    wait: 3800,
    scrubPii: true,
  },
  {
    file: 'screenshot-4.png',
    url: `${BASE}/wp-admin/edit.php?post_type=nestform&page=nestform-entries`,
    wait: 2800,
    scrubPii: true,
  },
  {
    file: 'screenshot-5.png',
    url: `${BASE}/#support`,
    wait: 3500,
    front: true,
  },
  {
    file: 'screenshot-6.png',
    url: `${BASE}/wp-admin/edit.php?post_type=nestform&page=nestform-integrations`,
    wait: 2500,
    scrubPii: true,
  },
  {
    file: 'screenshot-7.png',
    url: `${BASE}/wp-admin/post.php?post=${PRO_FORM_ID}&action=edit`,
    wait: 3800,
    scrubPii: true,
  },
];

async function scrubPii(page) {
  await page.evaluate(() => {
    const demos = [
      ['Alex Rivera', 'alex@example.com'],
      ['Sam Chen', 'sam@example.com'],
      ['Jordan Lee', 'jordan@example.com'],
      ['Taylor Brooks', 'taylor@example.com'],
      ['Casey Morgan', 'casey@example.com'],
      ['Riley Quinn', 'riley@example.com'],
      ['Jamie Ortiz', 'jamie@example.com'],
      ['Drew Patel', 'drew@example.com'],
      ['Morgan Blake', 'morgan@example.com'],
      ['Avery Kim', 'avery@example.com'],
      ['Cameron Diaz', 'cameron@example.com'],
      ['Reese Park', 'reese@example.com'],
      ['Skyler Ng', 'skyler@example.com'],
      ['Peyton Shaw', 'peyton@example.com'],
      ['Harper Cole', 'harper@example.com'],
      ['Quinn Hayes', 'quinn@example.com'],
      ['Rowan Bailey', 'rowan@example.com'],
      ['Finley Cruz', 'finley@example.com'],
      ['Emerson Day', 'emerson@example.com'],
      ['Parker West', 'parker@example.com'],
    ];
    let i = 0;
    const next = () => demos[i++ % demos.length];
    const emailRe = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi;

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let n;
    while ((n = walker.nextNode())) nodes.push(n);
    nodes.forEach((textNode) => {
      const raw = textNode.nodeValue || '';
      if (!raw.includes('@')) return;
      emailRe.lastIndex = 0;
      if (!emailRe.test(raw)) return;
      emailRe.lastIndex = 0;
      const demo = next();
      textNode.nodeValue = raw.replace(emailRe, demo[1]);
    });

    const contactCells = document.querySelectorAll(
      'td[data-colname="Contact"], td.column-contact, table tbody tr td:nth-child(2)'
    );
    contactCells.forEach((cell) => {
      const demo = next();
      cell.innerHTML = `<strong>${demo[0]}</strong><br><span>${demo[1]}</span>`;
    });
  });
  await page.waitForTimeout(400);
}

async function hideChrome(page) {
  await page.addStyleTag({ content: CHROME_CSS }).catch(() => {});
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({
    headless: true,
    channel: 'chrome',
    ignoreHTTPSErrors: true,
  });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  const loginUrl = `${BASE}/login/`;
  await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 90000 });
  console.log('login page', page.url());
  const userSel = (await page.locator('#auth-log').count()) ? '#auth-log' : '#user_login';
  const passSel = (await page.locator('#auth-pwd').count()) ? '#auth-pwd' : '#user_pass';
  const submitSel = (await page.locator('button.auth__submit, button[type="submit"]').count())
    ? 'button.auth__submit, button[type="submit"]'
    : '#wp-submit';
  await page.fill(userSel, USER);
  await page.fill(passSel, PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
    page.click(submitSel),
  ]);

  if (page.url().includes('/login') || page.url().includes('wp-login.php')) {
    const err = await page
      .locator('.auth__error, #login_error, .notice-error')
      .textContent()
      .catch(() => 'login failed');
    console.error(String(err || 'login failed'), page.url());
    await page.screenshot({ path: path.join(OUT, '_login-debug.png'), fullPage: true });
    await browser.close();
    process.exit(1);
  }

  for (const shot of shots) {
    await page.goto(shot.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(shot.wait);
    await hideChrome(page);
    await page.waitForTimeout(300);

    if (shot.front) {
      await page.waitForTimeout(1800);
      const support = page.locator('[data-block="nestform-support"], .nestform-support').first();
      if (await support.count()) {
        await support.scrollIntoViewIfNeeded();
        await page.waitForTimeout(800);
        const box = await support.boundingBox();
        if (box) {
          const target = path.join(OUT, shot.file);
          await page.screenshot({
            path: target,
            clip: {
              x: Math.max(0, box.x - 24),
              y: Math.max(0, box.y - 24),
              width: Math.min(box.width + 48, 1400),
              height: Math.min(box.height + 48, 900),
            },
          });
          console.log('saved', target);
          continue;
        }
      }
    }

    if (shot.scrubPii) {
      await scrubPii(page);
    }

    const target = path.join(OUT, shot.file);
    await page.screenshot({ path: target, fullPage: false });
    console.log('saved', target);
  }

  await browser.close();
  console.log('done');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
