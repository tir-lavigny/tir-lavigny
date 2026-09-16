import { test, expect } from '@playwright/test';

test.describe('Event detail page', () => {
    test('displays event information from home page navigation', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('home-events-section').getByTestId('event-card').first().getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays event from events list navigation', async ({ page }) => {
        await page.goto('/events');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('event-card').first().getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('displays event content or fallback', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('home-events-section').getByTestId('event-card').first().getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByTestId('event-content').or(page.getByTestId('event-no-content'))).toBeVisible();
    });
});
