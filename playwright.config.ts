import { defineConfig, devices } from '@playwright/test';

process.env.E2E_ADMIN_EMAIL ??= 'browser-platform-admin@example.test';
process.env.E2E_ADMIN_PASSWORD ??= 'Browser-Test-Password-2026';
process.env.E2E_USER_PASSWORD ??= 'Browser-User-Password-2026';

export default defineConfig({
 testDir: './tests/Browser', timeout: 30_000, fullyParallel: false,
 use: { baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
 reporter: [['list'], ['html', { open: 'never' }]],
 projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
