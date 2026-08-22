import { expect, test } from "@playwright/test";

test("eligible lodge member sees volunteer signup on public event detail", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill("volunteer@lodge-a.test");
    await page.getByLabel("Password").fill("password");
    await page.getByRole("button", { name: "Log in" }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto("/l/lodge-a/events/2");
    await expect(
        page.getByRole("heading", { name: "Volunteer staffing" }),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Volunteer" }).first(),
    ).toBeVisible();
});

test("event manager configures volunteer staffing and anonymous event detail omits it", async ({
    page,
}) => {
    test.setTimeout(90_000);

    const suffix = Date.now().toString();
    const password = process.env.E2E_ADMIN_PASSWORD!;
    const lodgeSlug = `staffing-${suffix}`;

    await page.goto("/login");
    await page.getByLabel("Email address").fill(process.env.E2E_ADMIN_EMAIL!);
    await page.getByLabel("Password").fill(password);
    await page.getByRole("button", { name: "Log in" }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto("/platform/lodges/create");
    await page.getByLabel("Name").fill(`Staffing Lodge ${suffix}`);
    await page.getByLabel("Number").fill(`S-${suffix}`);
    await page.getByLabel("Slug").fill(lodgeSlug);
    await page.getByLabel("City").fill("Evansville");
    await page.getByLabel("State").fill("IN");
    await page.getByLabel("Jurisdiction").fill("Indiana");
    await page.getByLabel("Physical address").fill("100 Staffing Street");
    await page.getByLabel("Timezone").fill("America/Chicago");
    await page.getByLabel("Public email").fill(`${lodgeSlug}@example.test`);
    await page.getByRole("button", { name: "Save lodge" }).click();
    await expect(page).toHaveURL(/\/platform\/lodges\/\d+\/edit$/);
    const lodgeId = page.url().match(/lodges\/(\d+)\/edit$/)?.[1];
    expect(lodgeId).toBeTruthy();

    await page.goto(`/lodges/${lodgeId}/events/create`);
    await page.getByLabel("Title").fill("Volunteer Setup Event");
    await page.getByLabel("Slug").fill(`volunteer-setup-${suffix}`);
    await page.getByLabel("Starts").fill("2026-12-01T19:00");
    await page.getByLabel("Duration (minutes)").fill("60");
    await page.getByRole("button", { name: "Save event" }).click();
    await expect(page).toHaveURL(
        new RegExp(`/lodges/${lodgeId}/events/\\d+/edit$`),
    );

    await expect(
        page.getByRole("heading", { name: "Volunteer staffing" }),
    ).toBeVisible();
    await page.getByLabel("Position name").fill("Setup");
    await page.getByLabel("Needed").fill("2");
    await page.getByRole("button", { name: "Add staffing position" }).click();
    await expect(page.getByText("Setup", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Publish event" }).click();

    await page.context().clearCookies();
    await page.goto(`/l/${lodgeSlug}/events`);
    await page.getByRole("link", { name: "Volunteer Setup Event" }).click();
    await expect(page.getByText("Volunteer staffing")).toHaveCount(0);
});
