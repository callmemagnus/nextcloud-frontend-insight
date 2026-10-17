import { test, expect, type Page } from "@playwright/test";
import {
  ANONYMOUS, APP_URL, adminOcs, getJson, loginAs, reportEvent, requestToken, saveSettings, type Settings,
} from "./helpers";

const USER = "fei-e2e-user";
const PASSWORD = "Fei-e2e-Passw0rd!2026";
const GROUP = "fei-e2e-viewers";
// Not APP_URL: while the extra group exists, the event browser may 403 and its page has no token
const DASHBOARD = "/index.php/apps/dashboard/";

let original: Settings;
const seeded = "access-test secret " + Date.now();

async function setAllowedGroups(page: Page, groups: string[]) {
  const token = await requestToken(page, DASHBOARD);
  const response = await saveSettings(page, token, { ...original, allowed_groups: groups });
  expect(response.status()).toBe(200);
}

test.describe.serial("non-admin access", () => {
  test.beforeAll(async ({ playwright, browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const ocs = await adminOcs(playwright, baseURL);
    await ocs.delete(`/ocs/v2.php/cloud/users/${USER}`); // leftovers from an aborted run
    await ocs.delete(`/ocs/v2.php/cloud/groups/${GROUP}`);
    expect((await ocs.post("/ocs/v2.php/cloud/groups", { form: { groupid: GROUP } })).ok()).toBe(true);
    expect((await ocs.post("/ocs/v2.php/cloud/users", { form: { userid: USER, password: PASSWORD } })).ok()).toBe(true);
    expect((await ocs.post(`/ocs/v2.php/cloud/users/${USER}/groups`, { form: { groupid: GROUP } })).ok()).toBe(true);
    await ocs.dispose();

    const anon = await playwright.request.newContext({ baseURL, storageState: ANONYMOUS });
    expect((await reportEvent(anon, baseURL, { message: seeded })).status()).toBe(204);
    await anon.dispose();

    // A second group disables the "only admin group exists" fallback, so pin the allowed groups explicitly
    const admin = await browser.newPage({ baseURL, storageState: testInfo.project.use.storageState });
    original = await getJson(admin, await requestToken(admin, DASHBOARD), "/settings");
    await setAllowedGroups(admin, ["admin"]);
    await admin.close();
  });

  test.afterAll(async ({ playwright, browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const admin = await browser.newPage({ baseURL, storageState: testInfo.project.use.storageState });
    if (original) await saveSettings(admin, await requestToken(admin, DASHBOARD), original);
    await admin.close();

    const ocs = await adminOcs(playwright, baseURL);
    await ocs.delete(`/ocs/v2.php/cloud/users/${USER}`);
    await ocs.delete(`/ocs/v2.php/cloud/groups/${GROUP}`);
    await ocs.dispose();
  });

  test("users outside the allowed groups cannot open the event browser", async ({ browser, baseURL }) => {
    const userPage = await loginAs(browser, baseURL!, USER, PASSWORD);
    const response = await userPage.goto(APP_URL);
    expect(response?.status()).toBe(403);
    await userPage.context().close();
  });

  test("non-admin users cannot read events or stats", async ({ browser, baseURL }) => {
    const userPage = await loginAs(browser, baseURL!, USER, PASSWORD);
    const token = await requestToken(userPage, DASHBOARD);
    for (const path of ["/api/1.0/events", "/api/1.0/stats"]) {
      const response = await userPage.request.get(`${APP_URL}${path}`, { headers: { requesttoken: token } });
      expect(response.status(), path).toBe(403);
      expect(await response.text()).not.toContain(seeded);
    }
    await userPage.context().close();
  });

  test("non-admin users cannot purge events or change settings", async ({ browser, baseURL, page }) => {
    const userPage = await loginAs(browser, baseURL!, USER, PASSWORD);
    const token = await requestToken(userPage, DASHBOARD);
    const headers = { requesttoken: token, Accept: "application/json" };

    const purge = await userPage.request.delete(`${APP_URL}/api/1.0/events/purge`, { headers });
    expect(purge.status()).toBe(403);
    const save = await userPage.request.post(`${APP_URL}/settings`, {
      headers,
      form: { allowed_groups: GROUP, retention_hours: 1 },
    });
    expect(save.status()).toBe(403);
    await userPage.context().close();

    const adminToken = await requestToken(page, DASHBOARD);
    const events = await getJson(page, adminToken, `/api/1.0/events?q=${encodeURIComponent(seeded)}`);
    expect(events.totalItems).toBe(1);
    expect((await getJson(page, adminToken, "/settings")).allowed_groups).toEqual(["admin"]);
  });

  test("users in an allowed group can browse events without admin actions", async ({ browser, baseURL, page }) => {
    test.fail(true, "Known bug: EventBrowserController and ApiController lack #[NoAdminRequired], so Nextcloud returns 403 to every non-admin");

    await setAllowedGroups(page, ["admin", GROUP]);
    try {
      const userPage = await loginAs(browser, baseURL!, USER, PASSWORD);
      const response = await userPage.goto(APP_URL);
      expect(response?.status()).toBe(200);
      await expect(
        userPage.locator(".col-msg .mwb-copy-text").filter({ hasText: seeded })
      ).toBeVisible({ timeout: 10_000 });
      await expect(userPage.getByRole("button", { name: "Purge all events" })).toHaveCount(0);
      await userPage.context().close();
    } finally {
      await setAllowedGroups(page, ["admin"]);
    }
  });
});
