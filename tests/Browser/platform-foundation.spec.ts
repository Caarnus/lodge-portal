import { test, expect } from '@playwright/test';
test('registration requires a home lodge and reaches pending approval', async ({ page }) => {
    const email = `registration-${Date.now()}@example.test`;

    await page.goto('/register');
    await expect(page.getByRole('heading', { name: 'Create an account' })).toBeVisible();
    await page.getByLabel('Home lodge').selectOption({ index: 1 });
    await page.getByLabel('Name').fill('Registration Candidate');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('BrowserTest!2026');
    await page.getByLabel('Confirm password').fill('BrowserTest!2026');
    await page.getByRole('button', { name: 'Create account' }).click();

    await expect(page).toHaveURL(/\/pending$/, { timeout: 15_000 });
    await expect(page.getByRole('heading', { name: 'Registration pending' })).toBeVisible();
    await expect(page.getByText(/verify your email/i)).toBeVisible();
});
test('login screen is available', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: /log in/i })).toBeVisible();
});

test('platform and lodge administrators complete the two-lodge isolation flow', async ({ page, context }) => {
    test.setTimeout(180_000);
    test.skip(!process.env.E2E_ADMIN_EMAIL, 'Run through the Docker browser service for the seeded administrator flow.');

    const suffix = Date.now().toString();
    const adminPassword = process.env.E2E_ADMIN_PASSWORD!;
    const userPassword = process.env.E2E_USER_PASSWORD!;

    const login = async (email: string, password: string) => {
        await context.clearCookies();
        await page.goto('/login');
        await page.getByLabel('Email address').fill(email);
        await page.getByLabel('Password').fill(password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
    };

    const createLodge = async (name: string, number: string, slug: string) => {
        await page.goto('/platform/lodges/create');
        await page.getByLabel('Name').fill(name);
        await page.getByLabel('Number').fill(number);
        await page.getByLabel('Slug').fill(slug);
        await page.getByLabel('City').fill('Evansville');
        await page.getByLabel('State').fill('IN');
        await page.getByLabel('Jurisdiction').fill('Indiana');
        await page.getByLabel('Physical address').fill('100 Browser Test Street');
        await page.getByLabel('Timezone').fill('America/Chicago');
        await page.getByLabel('Public email').fill(`${slug}@example.test`);
        await page.getByRole('button', { name: 'Save lodge' }).click();
        await expect(page).toHaveURL(/\/platform\/lodges\/\d+\/edit$/);
        return page.url().match(/lodges\/(\d+)\/edit$/)![1];
    };

    const assignAdmin = async (email: string) => {
        await page.getByPlaceholder('Email').fill(email);
        await page.getByRole('button', { name: 'Assign or create administrator' }).click();
        await expect(page.getByText(email, { exact: false })).toBeVisible();
    };

    await login(process.env.E2E_ADMIN_EMAIL!, adminPassword);
    const sidebarBox = await page.locator('[data-sidebar="sidebar"]').first().boundingBox();
    const contentBox = await page.locator('header').first().boundingBox();
    expect(sidebarBox).not.toBeNull();
    expect(contentBox).not.toBeNull();
    expect(contentBox!.x).toBeGreaterThanOrEqual(sidebarBox!.x + sidebarBox!.width);
    const dashboardNav = page.locator('[data-sidebar="menu-button"]').filter({ hasText: 'Dashboard' });
    await expect(dashboardNav).toHaveAttribute('href', '/dashboard');
    await expect(page.getByRole('link', { name: 'Platform lodges' })).toHaveAttribute('href', '/platform/lodges');
    await expect(page.getByRole('link', { name: 'Registrations' })).toHaveAttribute('href', '/registrations');
    await page.getByRole('link', { name: 'Platform lodges' }).click();
    await expect(page).toHaveURL(/\/platform\/lodges$/);
    await expect(page.getByRole('heading', { name: 'Lodges' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Create lodge' })).toBeVisible();
    await expect(page.getByRole('link', { name: /^Edit / }).first()).toBeVisible();
    await page.getByRole('link', { name: 'Registrations' }).click();
    await expect(page).toHaveURL(/\/registrations$/);
    const approveButton = page.getByRole('button', { name: /^Approve / }).first();
    await expect(approveButton).toBeVisible();
    await expect(page.getByRole('button', { name: /^Reject / }).first()).toBeVisible();
    const documentHeightBeforeTooltip = await page.evaluate(() => document.documentElement.scrollHeight);
    await approveButton.hover();
    await expect(page.getByRole('tooltip')).toHaveCount(0);
    await expect(page.getByRole('tooltip')).toContainText('Approve');
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollHeight)).toBe(documentHeightBeforeTooltip);
    await dashboardNav.click();
    await expect(page).toHaveURL(/\/dashboard$/);

    const lodgeAName = `Browser Lodge A ${suffix} with an intentionally long name for compact list previews`;
    const lodgeBName = `Browser Lodge B ${suffix}`;
    const lodgeAId = await createLodge(lodgeAName, `A-${suffix}`, `browser-a-${suffix}`);
    await assignAdmin('lodge-a-admin@example.test');
    await assignAdmin('multi-lodge-admin@example.test');
    const lodgeBId = await createLodge(lodgeBName, `B-${suffix}`, `browser-b-${suffix}`);
    await assignAdmin('lodge-b-admin@example.test');
    await assignAdmin('multi-lodge-admin@example.test');

    const publishTemplateHome = async (lodgeId: string, lodgeSlug: string, lodgeName: string) => {
        await page.goto(`/lodges/${lodgeId}/website`);
        await page.getByRole('button', { name: 'Apply default template' }).click();
        const homeRow = page.locator('article').filter({ hasText: /^Home/ }).first();
        await homeRow.getByRole('link', { name: 'Edit page' }).click();
        const previewUrl = await page.getByRole('link', { name: 'Preview draft' }).getAttribute('href');
        await page.goto(previewUrl!);
        await expect(page.getByText(/Draft preview/)).toBeVisible();
        await page.goBack();
        await page.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`/lodges/${lodgeId}/website$`));
        const aboutRow = page.locator('article').filter({ hasText: /^About/ }).first();
        await aboutRow.getByRole('link', { name: 'Edit page' }).click();
        await page.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`/lodges/${lodgeId}/website$`));
        await page.goto(`/l/${lodgeSlug}`);
        await expect(page.getByRole('heading', { name: lodgeName, exact: true }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'About' })).toBeVisible();
    };

    await publishTemplateHome(lodgeAId, `browser-a-${suffix}`, lodgeAName);
    await publishTemplateHome(lodgeBId, `browser-b-${suffix}`, lodgeBName);
    await page.goto(`/l/browser-a-${suffix}`);
    await expect(page.getByText(lodgeBName)).toHaveCount(0);

    await page.setViewportSize({ width: 420, height: 800 });
    await page.goto('/platform/lodges');
    const expandableLodgeName = page.getByRole('button', { name: 'Expand lodge name' }).first();
    await expect(expandableLodgeName).toBeVisible();
    await expandableLodgeName.click();
    await expect(page.getByRole('button', { name: 'Collapse lodge name' }).first()).toHaveAttribute('aria-expanded', 'true');
    await page.setViewportSize({ width: 1280, height: 720 });

    await login('lodge-a-admin@example.test', userPassword);
    await page.goto(`/lodges/${lodgeAId}/settings`);
    await page.getByLabel('Name').fill(`${lodgeAName} Updated`);
    await page.getByLabel('Primary color').fill('#123456');
    await page.getByRole('button', { name: 'Save lodge' }).click();
    await expect(page.getByLabel('Name')).toHaveValue(`${lodgeAName} Updated`);

    const officerEmail = `browser-officer-${suffix}@example.test`;
    await page.goto(`/lodges/${lodgeAId}/people/create`);
    await page.getByLabel('First name').fill('Hiram');
    await page.getByLabel('Last name').fill(`Browser ${suffix}`);
    await page.getByLabel('Preferred name').fill('Hiram');
    await page.getByLabel('Email').fill(officerEmail);
    await page.getByRole('button', { name: 'Create person and membership' }).click();
    await expect(page).toHaveURL(new RegExp(`/lodges/${lodgeAId}/people/\\d+/edit$`));
    await page.getByRole('button', { name: 'Create a new non-member relative' }).click();
    await page.getByPlaceholder('First name').fill('Alex');
    await page.getByPlaceholder('Last name').fill(`Browser ${suffix}`);
    await page.getByLabel('Relationship type').selectOption({ label: 'Spouse' });
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.getByText(new RegExp(`Hiram Browser ${suffix} is spouse of Alex Browser ${suffix}`, 'i'))).toBeVisible();
    await page.getByRole('button', { name: 'Invite account' }).click();
    await expect(page.getByRole('button', { name: 'Invite account' })).toHaveCount(0);
    await expect(page.getByText(`Linked to ${officerEmail}`)).toBeVisible();

    await page.goto(`/lodges/${lodgeAId}/officers`);
    await page.getByLabel('Secretary member').selectOption({ label: `Hiram Browser ${suffix}` });
    await page.getByRole('button', { name: 'Save Secretary' }).click();
    await expect(page.getByRole('dialog', { name: 'Review officer access' })).toBeVisible();
    await page.getByRole('button', { name: 'Not now' }).click();

    await page.goto(`/lodges/${lodgeAId}/website`);
    const officersRow = page.locator('article').filter({ hasText: /^Officers/ }).first();
    await officersRow.getByRole('link', { name: 'Edit page' }).click();
    await page.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/lodges/${lodgeAId}/website$`));
    const publishedOfficersRow = page.locator('article').filter({ hasText: /^Officers/ }).first();
    const publicOfficersPage = publishedOfficersRow.getByRole('link', { name: 'View published page' });
    await expect(publicOfficersPage).toBeVisible();
    const publicOfficersHref = await publicOfficersPage.getAttribute('href');
    expect(publicOfficersHref).not.toBeNull();
    await page.goto(publicOfficersHref!);
    await expect(page.getByRole('heading', { name: `Hiram Browser ${suffix}` })).toBeVisible();
    await expect(page.getByText(officerEmail)).toHaveCount(0);

    expect((await page.goto(`/lodges/${lodgeBId}/settings`))?.status()).toBe(403);

    await login('multi-lodge-admin@example.test', userPassword);
    const activeLodge = page.getByLabel('Active lodge');
    await activeLodge.selectOption(lodgeAId);
    await expect(activeLodge).toHaveValue(lodgeAId);
    await activeLodge.selectOption(lodgeBId);
    await expect(activeLodge).toHaveValue(lodgeBId);
    expect((await page.goto(`/lodges/${lodgeAId}/settings`))?.status()).toBe(200);
    expect((await page.goto(`/lodges/${lodgeBId}/settings`))?.status()).toBe(200);
});
