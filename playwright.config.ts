import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
 testDir: './tests/Browser', timeout: 30_000, fullyParallel: false,
 use: { baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
 reporter: [['list'], ['html', { open: 'never' }]],
 projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
