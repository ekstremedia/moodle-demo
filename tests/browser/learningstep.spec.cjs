const {test, expect} = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {randomBytes} = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');

function fixture(action, username) {
    return execFileSync('docker', ['compose', 'exec', '-T', '-u', 'www-data', 'web',
        'php', '/dev/stdin', action, username], {
        cwd: root, input: readFileSync(path.join(root, 'tests/fixtures/learningstep.php')), encoding: 'utf8',
    });
}
async function login(page, username) {
    await page.goto('/login/index.php');
    await page.locator('#username').fill(username);
    await page.locator('#password').fill('Learning123!');
    await page.locator('#loginbtn').click();
    await expect(page.locator('.learningstep')).toBeVisible();
}
let username;
test.beforeEach(() => {
    username = 'test-learningstep-' + randomBytes(6).toString('hex');
    fixture('create', username);
});
test.afterEach(() => fixture('delete', username));

test('keyboard flow works without JavaScript, including completion and reset', async ({browser, baseURL}) => {
    const context = await browser.newContext({baseURL, javaScriptEnabled: false});
    const page = await context.newPage();
    try {
        await login(page, username);
        const block = page.locator('.learningstep');
        for (let step = 0; step < 5; step++) {
            // Focus the question, then use only Tab, Space and Enter within the exercise.
            await page.locator('#learningstep').focus();
            await page.keyboard.press('Tab');
            await expect(block.getByRole('radio').first()).toBeFocused();
            await page.keyboard.press('Space');
            await page.keyboard.press('Tab');
            await expect(block.getByRole('button', {name: 'Se forklaring'})).toBeFocused();
            await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
            await expect(block.locator('.learningstep-feedback')).toBeVisible();
            await expect(page.locator('#learningstep')).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(block.getByRole('button', {name: 'Neste steg'})).toBeFocused();
            await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
            await expect(block.getByText(`${step + 1} av 5 steg gjennomgått`)).toBeVisible();
        }
        await expect(block.getByText('Fem små steg – ta dem med inn i neste kurs.')).toBeVisible();
        await page.keyboard.press('Tab');
        await expect(block.getByRole('button', {name: 'Nullstill mine steg'})).toBeFocused();
        await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
        await expect(block.getByText('0 av 5 steg gjennomgått')).toBeVisible();
    } finally {
        await context.close();
    }
});

test('question and feedback pass scoped accessibility checks and fit a narrow screen', async ({page}) => {
    await page.setViewportSize({width: 320, height: 900});
    await login(page, username);
    const block = page.locator('.learningstep');
    for (const answered of [false, true]) {
        if (answered) {
            await block.getByRole('radio').last().check();
            await block.getByRole('button', {name: 'Se forklaring'}).click();
            await expect(block.locator('.learningstep-feedback')).toBeVisible();
        }
        const results = await new AxeBuilder({page}).include('.block_learningstep')
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        expect(results.violations).toEqual([]);
        expect(await block.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
    }
});
