import { expect, test } from '@playwright/test';

test.describe('Event detail page', () => {
    test('navigates from home page to event detail with location', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const card = page.getByTestId('home-events-section').getByTestId('event-card').first();
        await expect(card).toBeVisible();
        await card.getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByText('Stand de tir de Lavigny').first()).toBeVisible();
    });

    test('event detail page displays content and address', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const card = page.getByTestId('home-events-section').getByTestId('event-card').first();
        await expect(card).toBeVisible();
        await card.getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByTestId('event-content').or(page.getByTestId('event-no-content'))).toBeVisible();
        await expect(page.getByText('1175 Lavigny').first()).toBeVisible();
    });

    test('event detail from events list shows location and content', async ({ page }) => {
        await page.goto('/events');
        await page.waitForLoadState('networkidle');

        await expect(page.getByTestId('event-card').first()).toBeVisible();
        await page.getByTestId('event-card').first().getByRole('link').click();
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByTestId('event-content').or(page.getByTestId('event-no-content'))).toBeVisible();
        await expect(page.getByText('Stand de tir de Lavigny').first()).toBeVisible();
    });
});
