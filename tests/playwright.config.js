const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './e2e',
    globalSetup: './global-setup.js',
    use: {
        baseURL:      process.env.WP_BASE_URL,
        storageState: 'auth.json',
        screenshot:   'only-on-failure',
        video:        'retain-on-failure',
    },
    timeout: 60000,
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ],
});
