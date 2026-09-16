import { expect, test } from '@playwright/test';

test.describe('Shootings ODS page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/shootings/ods');
        await page.waitForLoadState('networkidle');
    });

    test('displays hero section', async ({ page }) => {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays shooting program information', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /Venez effectuer/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Programme/i })).toBeVisible();
    });

    test('displays required items as a list', async ({ page }) => {
        await expect(page.getByTestId('required-items-list')).toBeVisible();
        await expect(page.getByTestId('required-items-list').getByRole('listitem')).toHaveCount(6);
    });

    test('displays next dates section', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /Prochaines dates/ })).toBeVisible();
        await expect(page.getByTestId('next-dates-list')).toBeVisible();
    });
});

test.describe('Shootings ODS page navigation', () => {
    test('/shootings redirects to /shootings/ods', async ({ page }) => {
        await page.goto('/shootings');
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/shootings\/ods/);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });
});
