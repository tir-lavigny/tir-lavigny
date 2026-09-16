import { expect, test } from '@playwright/test';

test.describe('Navigation', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('header contains main navigation links', async ({ page }) => {
        const nav = page.locator('nav').first();
        await expect(nav.getByRole('link', { name: 'Accueil' })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Événements' })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Tirs militaires' })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Tir fédéral en campagne' })).toBeVisible();
    });

    test('nav link to events page works', async ({ page }) => {
        await page.locator('nav').first().getByRole('link', { name: 'Événements' }).click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/events/);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('nav link to shootings page works', async ({ page }) => {
        await page.locator('nav').first().getByRole('link', { name: 'Tirs militaires' }).click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/shootings/);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('nav link to shootings field page works', async ({ page }) => {
        await page.locator('nav').first().getByRole('link', { name: 'Tir fédéral en campagne' }).click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/shootings\/field/);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('logo links back to home', async ({ page }) => {
        await page.goto('/events');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('site-logo').click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL('/');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });
});
