import { expect, test } from '@playwright/test';

test.describe('ShootingsField page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/shootings/field');
        await page.waitForLoadState('networkidle');
    });

    test('displays hero section', async ({ page }) => {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays field shooting information sections', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /Venez participer/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Programme/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Plus d'informations/i })).toBeVisible();
    });

    test('displays programme details', async ({ page }) => {
        await expect(page.getByTestId('programme-list')).toBeVisible();
        await expect(page.getByTestId('programme-list').getByRole('listitem')).toHaveCount(3);
    });

    test('displays next dates section', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /Prochaines dates/ })).toBeVisible();
        await expect(page.getByTestId('next-dates-list')).toBeVisible();
    });
});
