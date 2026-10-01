const {defineConfig} = require('@playwright/test');
module.exports = defineConfig({
    testDir: __dirname,
    testMatch: '*.spec.cjs',
    workers: 1,
    timeout: 60000,
    use: {
        baseURL: process.env.MOODLE_TEST_URL || 'http://localhost:8080',
        browserName: 'chromium',
        trace: 'retain-on-failure',
    },
    outputDir: '../../test-results',
});
