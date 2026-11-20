import { test, expect } from '@playwright/test';

test.describe('Cambodia Game Top-Up Storefront E2E', () => {
  test('guest can browse Mobile Legends, verify player, create order, simulate payment, and track completion', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByText('Mobile Legends: Bang Bang')).toBeVisible();

    await page.getByText('Mobile Legends: Bang Bang').first().click();
    await expect(page).toHaveURL(/\/games\/mobile-legends/);

    await page.fill('#field-user_id', '123456789');
    await page.fill('#field-zone_id', '2014');

    await page.getByRole('button', { name: /Proceed to Checkout|បន្តទៅការទូទាត់/ }).click();
    await expect(page).toHaveURL(/\/checkout\/KTU-/);

    await page.getByRole('button', { name: /Paid|ទូទាត់ជោគជ័យ/ }).click();
    await expect(page).toHaveURL(/\/track\/KTU-/);
    await expect(page.getByText('completed', { exact: false })).toBeVisible();
  });
});
