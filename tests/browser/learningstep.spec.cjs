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
async function login(page, username, useLoginForm = false) {
    if (useLoginForm) {
        await page.goto('/login/index.php');
        await page.locator('#username').fill(username);
        await page.locator('#password').fill('Learning123!');
        await page.locator('#loginbtn').click();
    } else {
        // Authenticate through Moodle with this browser context's cookie jar. This keeps
        // plugin tests independent of JavaScript initialisation on the core login form.
        const form = await page.request.get('/login/index.php');
        expect(form.ok(), 'Moodle login form must load').toBe(true);
        const token = (await form.text()).match(/name="logintoken" value="([^"]+)"/);
        expect(token, 'Moodle login form must contain its CSRF token').not.toBeNull();
        const response = await page.request.post('/login/index.php', {
            form: {username, password: 'Learning123!', logintoken: token[1]},
        });
        expect(response.ok(), 'Moodle must accept the login request').toBe(true);
        expect(new URL(response.url()).pathname, 'Login must leave the login page').not.toBe('/login/index.php');
        await page.goto('/my/');
    }
    await expect(page).toHaveURL(/\/my\/(?:[?#].*)?$/);
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
        await login(page, username, true);
        const block = page.locator('.learningstep');
        for (let step = 0; step < 5; step++) {
            // Focus the question, then use only Tab, Space and Enter within the exercise.
            await page.locator('#learningstep').focus();
            await page.keyboard.press('Tab');
            await expect(block.getByRole('radio').first()).toBeFocused();
            await page.keyboard.press('Space');
            await page.keyboard.press('Tab');
            await expect(block.getByRole('button', {name: 'Svar og se forklaring'})).toBeFocused();
            await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
            await expect(block.locator('.learningstep-feedback')).toBeVisible();
            await expect(page.locator('#learningstep')).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(block.getByRole('button', {name: step === 4 ? 'Fullfør øvelsen' : 'Neste steg'})).toBeFocused();
            await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
            await expect(block.getByText(`${step + 1} av 5 steg gjennomgått`)).toBeVisible();
        }
        await expect(block.getByText('Øvelsen er fullført')).toBeVisible();
        await page.keyboard.press('Tab');
        await expect(block.getByRole('link', {name: 'Utforsk ressurser hos Statped'})).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(block.getByRole('button', {name: 'Ta oppgavene på nytt'})).toBeFocused();
        await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.keyboard.press('Enter')]);
        await expect(block.getByText('0 av 5 steg gjennomgått')).toBeVisible();
    } finally {
        await context.close();
    }
});

test('question and feedback pass scoped accessibility checks and fit a narrow screen', async ({page}) => {
    await page.setViewportSize({width: 320, height: 900});
    await login(page, username);
    await page.goto('/my/?lang=en');
    await expect(page.locator('.block_learningstep')).toHaveAttribute('data-learningstep-ready', '1');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    const block = page.locator('.learningstep');
    await expect(block).toHaveAttribute('lang', 'nb');
    for (const answered of [false, true]) {
        if (answered) {
            const option = block.getByRole('radio').last();
            const submit = block.getByRole('button', {name: 'Svar og se forklaring'});
            await expect(submit).toBeVisible();
            await option.check();
            await expect(option).toBeChecked();
            await expect(submit).toBeVisible();
            await expect(submit).toBeEnabled();
            await expect(block.locator('.learningstep-feedback')).toHaveCount(0);
            await block.getByRole('button', {name: 'Svar og se forklaring'}).click();
            await expect(block.locator('.learningstep-feedback')).toBeVisible();
        }
        const results = await new AxeBuilder({page}).include('.block_learningstep')
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        expect(results.violations).toEqual([]);
        expect(await block.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
    }
});


test('answers, completion and restart update in place without moving the page', async ({page}) => {
    await login(page, username);
    await expect(page.locator('.block_learningstep')).toHaveAttribute('data-learningstep-ready', '1');
    const block = page.locator('.learningstep');
    const initialUrl = page.url();
    await page.evaluate(() => { window.learningstepDocumentMarker = 'same-document'; });
    for (let step = 0; step < 5; step++) {
        await block.getByRole('radio').last().check();
        const submit = block.getByRole('button', {name: 'Svar og se forklaring'});
        await submit.scrollIntoViewIfNeeded();
        const scroll = await page.evaluate(() => window.scrollY);
        await submit.click();
        await expect(block.locator('.learningstep-feedback')).toBeVisible();
        await expect(page.locator('#learningstep')).toBeFocused();
        expect(await page.evaluate(() => window.scrollY)).toBeCloseTo(scroll, 0);
        expect(await page.evaluate(() => window.learningstepDocumentMarker)).toBe('same-document');
        expect(page.url()).toBe(initialUrl);
        await block.getByRole('button', {name: step === 4 ? 'Fullfør øvelsen' : 'Neste steg'}).click();
        if (step < 4) {
            await expect(block.getByRole('radio')).toHaveCount(3);
        }
    }
    await expect(block.getByRole('heading', {name: 'Øvelsen er fullført'})).toBeVisible();
    await expect(block.locator('.learningstep-topics li')).toHaveCount(5);
    await block.getByRole('button', {name: 'Ta oppgavene på nytt'}).click();
    await expect(block.getByText('0 av 5 steg gjennomgått')).toBeVisible();
    expect(await page.evaluate(() => window.learningstepDocumentMarker)).toBe('same-document');
});


test('failed submission keeps the chosen answer and allows retry', async ({page}) => {
    await login(page, username);
    await expect(page.locator('.block_learningstep')).toHaveAttribute('data-learningstep-ready', '1');
    const block = page.locator('.learningstep');
    await block.getByRole('radio').last().check();
    await page.route('**/blocks/learningstep/action.php', route => route.abort());
    await block.getByRole('button', {name: 'Svar og se forklaring'}).click();
    await expect(block.getByRole('alert')).toBeVisible();
    await expect(block.getByRole('radio').last()).toBeChecked();
    await expect(block.getByRole('button', {name: 'Svar og se forklaring'})).toBeEnabled();
    await page.unroute('**/blocks/learningstep/action.php');
    await block.getByRole('button', {name: 'Svar og se forklaring'}).click();
    await expect(block.locator('.learningstep-feedback')).toBeVisible();
    await expect(block.getByRole('alert')).toBeHidden();
});
