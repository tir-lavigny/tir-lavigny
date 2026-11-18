import { expect, test } from '@playwright/test';

test.describe('SEO metadata', () => {
    test('public page titles keep a single brand suffix', async ({ page }) => {
        await page.goto('/become-member');
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveTitle('Devenir membre - Société de tir de Lavigny');

        await page.goto('/privacy-policy');
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveTitle('Déclaration de protection des données - Société de tir de Lavigny');
    });

    test('client-side navigation updates title, description, canonical, and breadcrumbs', async ({ page }) => {
        await page.goto('/events');
        await page.waitForLoadState('networkidle');

        const initialTitle = await page.title();
        const initialDescription = await page.locator('meta[name="description"]').getAttribute('content');

        await page.getByTestId('events-filter-trigger').click();
        await page.getByRole('menuitem', { name: /Tous/ }).click();
        await page.waitForLoadState('networkidle');
        await page.locator('a').filter({ hasText: 'Voir' }).first().click();
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveURL(/\/events\/\d+$/);
        await expect(page).not.toHaveTitle(initialTitle);
        await expect(page).toHaveTitle(/ - Tir Lavigny$/);
        await expect(page.locator('meta[name="description"]')).not.toHaveAttribute('content', initialDescription ?? '');
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', page.url());
        await expect(page.locator('nav[aria-label="breadcrumb"]')).toContainText('Événements');
    });
});
