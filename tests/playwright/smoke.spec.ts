import { expect, Page, test } from '@playwright/test';

const customerUser = process.env.KC_CUSTOMER_USER;
const customerPass = process.env.KC_CUSTOMER_PASS;

const operatorUser = process.env.KC_OPERATOR_USER;
const operatorPass = process.env.KC_OPERATOR_PASS;

const adminUser = process.env.KC_ADMIN_USER;
const adminPass = process.env.KC_ADMIN_PASS;

/**
 * These URLs may need adjustment after the first CI run.
 */
const urls = {
  myAccount: '/my-account/',
  scanner: '/scanner/',
  adminQrCodes: '/wp-admin/admin.php?page=kerbcycle-qr-codes',
  adminQrHistory: '/wp-admin/admin.php?page=kerbcycle-qr-history',
  adminReports: '/wp-admin/admin.php?page=kerbcycle-reports',
  adminPickupExceptions: '/wp-admin/admin.php?page=kerbcycle-pickup-exceptions',
  adminSettings: '/wp-admin/admin.php?page=kerbcycle-settings',
};

async function expectNoVisibleWordPressErrors(page: Page) {
  const body = await page.locator('body').innerText();

  expect(body).not.toContain('There has been a critical error');
  expect(body).not.toContain('Fatal error');
  expect(body).not.toContain('Parse error');
  expect(body).not.toContain('Warning:');
  expect(body).not.toContain('Notice:');
  expect(body).not.toContain('Deprecated:');
  expect(body).not.toContain('Uncaught Error');
}

async function login(page: Page, username: string, password: string) {
  await page.goto('/wp-login.php');

  await expect(page.locator('#user_login')).toBeVisible();
  await expect(page.locator('#user_pass')).toBeVisible();

  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');

  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('body')).toBeVisible();
  await expectNoVisibleWordPressErrors(page);
}

test.describe('KerbCycle browser smoke tests', () => {
  test('homepage loads without visible WordPress/PHP errors', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('body')).toBeVisible();
    await expectNoVisibleWordPressErrors(page);
  });

  test('WordPress login page loads', async ({ page }) => {
    await page.goto('/wp-login.php');

    await expect(page.locator('#user_login')).toBeVisible();
    await expect(page.locator('#user_pass')).toBeVisible();
    await expect(page.locator('#wp-submit')).toBeVisible();

    await expectNoVisibleWordPressErrors(page);
  });

  test('customer can log in and access My Account', async ({ page }) => {
    test.skip(!customerUser || !customerPass, 'Customer credentials are not configured.');

    await login(page, customerUser!, customerPass!);

    await page.goto(urls.myAccount);

    await expect(page.locator('body')).toBeVisible();
    await expectNoVisibleWordPressErrors(page);

    await expect(page.locator('body')).toContainText(/dashboard|account|wallet|orders|logout/i);
  });

  test('operator can log in and access scanner page', async ({ page }) => {
    test.skip(!operatorUser || !operatorPass, 'Operator credentials are not configured.');

    await login(page, operatorUser!, operatorPass!);

    await page.goto(urls.scanner);

    await expect(page.locator('body')).toBeVisible();
    await expectNoVisibleWordPressErrors(page);

    await expect(page.locator('body')).toContainText(/scan|scanner|qr/i);
  });

  test('customer cannot access protected KerbCycle admin page', async ({ page }) => {
    test.skip(!customerUser || !customerPass, 'Customer credentials are not configured.');

    await login(page, customerUser!, customerPass!);

    await page.goto(urls.adminQrCodes);

    await expect(page.locator('body')).toBeVisible();
    await expectNoVisibleWordPressErrors(page);

    const body = await page.locator('body').innerText();

    expect(body).not.toContain('KerbCycle QR Codes');
    expect(body).not.toContain('Add QR Code');
    expect(body).not.toContain('Pickup Exceptions');
  });

  test('admin can load key KerbCycle admin pages', async ({ page }) => {
    test.skip(!adminUser || !adminPass, 'Admin credentials are not configured.');

    await login(page, adminUser!, adminPass!);

    const adminPages = [
      urls.adminQrCodes,
      urls.adminQrHistory,
      urls.adminReports,
      urls.adminPickupExceptions,
      urls.adminSettings,
    ];

    for (const adminPage of adminPages) {
      await page.goto(adminPage);

      await expect(page.locator('body')).toBeVisible();
      await expectNoVisibleWordPressErrors(page);
    }
  });
});