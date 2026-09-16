import { test, expect } from '@playwright/test';

test.describe('Home page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('displays hero section', async ({ page }) => {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays future events section', async ({ page }) => {
        const section = page.getByTestId('home-events-section');
        await expect(section).toBeVisible();
        await expect(section.getByRole('heading', { level: 2 })).toBeVisible();
        await expect(section.getByTestId('event-card').first()).toBeVisible();
    });

    test('displays shooting range section', async ({ page }) => {
        const section = page.getByTestId('home-range-section');
        await expect(section).toBeVisible();
        await expect(section.getByTestId('range-characteristics-list')).toBeVisible();
        await expect(section.getByTestId('range-characteristics-list').getByRole('listitem')).toHaveCount(4);
    });
});
