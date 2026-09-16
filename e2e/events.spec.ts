import { expect, test } from '@playwright/test';

test.describe('Events page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/events');
        await page.waitForLoadState('networkidle');
    });

    test('displays hero section', async ({ page }) => {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays filter button and event cards', async ({ page }) => {
        await expect(page.getByTestId('events-filter-trigger')).toBeVisible();
        await expect(page.getByTestId('event-card').first()).toBeVisible();
    });

    test('default filter shows future events', async ({ page }) => {
        const count = await page.getByTestId('event-card').count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('filter to past events shows past events', async ({ page }) => {
        await page.getByTestId('events-filter-trigger').click();
        await page.getByRole('menuitem', { name: /passés/ }).click();
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('event-card').first()).toBeVisible();
    });

    test('filter to all events shows more events', async ({ page }) => {
        const futureCount = await page.getByTestId('event-card').count();

        await page.getByTestId('events-filter-trigger').click();
        await page.getByRole('menuitem', { name: /Tous/ }).click();
        await page.waitForLoadState('networkidle');

        const allCount = await page.getByTestId('event-card').count();
        expect(allCount).toBeGreaterThanOrEqual(futureCount);
    });

    test('navigating to event detail from card works', async ({ page }) => {
        await page.getByTestId('events-filter-trigger').click();
        await page.getByRole('menuitem', { name: /Tous/ }).click();
        await page.waitForLoadState('networkidle');
        await page.getByTestId('event-card').first().getByRole('link').click();
        await page.waitForLoadState('networkidle');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });
});
