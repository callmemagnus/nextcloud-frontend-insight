import { test, expect } from "@playwright/test";
import { ANONYMOUS, APP_URL, getJson, reportEvent, requestToken, saveSettings, type Settings } from "./helpers";

test("events API filters with q, sorts and paginates with cursor", async ({ page, baseURL }) => {
  const prefix = "api-query-" + Date.now();
  for (const message of [`${prefix} b`, `${prefix} a`]) {
    expect((await reportEvent(page.request, baseURL!, { message })).status()).toBe(204);
  }
  const token = await requestToken(page);

  const asc = await getJson(page, token, `/api/1.0/events?q=${prefix}&sort=message&dir=asc`);
  expect(asc.totalItems).toBe(2);
  expect(asc.values.map((e: { message: string }) => e.message)).toEqual([`${prefix} a`, `${prefix} b`]);

  const desc = await getJson(page, token, `/api/1.0/events?q=${prefix}&sort=message&dir=desc`);
  expect(desc.values.map((e: { message: string }) => e.message)).toEqual([`${prefix} b`, `${prefix} a`]);

  const secondPage = await getJson(page, token, `/api/1.0/events?q=${prefix}&sort=message&dir=asc&limit=1&cursor=1`);
  expect(secondPage.totalItems).toBe(2);
  expect(secondPage.values).toHaveLength(1);
  expect(secondPage.values[0].message).toBe(`${prefix} b`);
});

// Lower bound is not testable over HTTP: Nextcloud answers 400 for limit < 1 before the controller runs.
test("events API caps limit at 100", async ({ page }) => {
  const token = await requestToken(page);
  expect((await getJson(page, token, "/api/1.0/events?limit=500")).limit).toBe(100);
});

test("events API ignores unknown sort columns", async ({ page }) => {
  const token = await requestToken(page);
  const data = await getJson(page, token, `/api/1.0/events?sort=${encodeURIComponent("message; DROP TABLE x")}`);
  expect(Array.isArray(data.values)).toBe(true);
});

test("stats API counts events by type", async ({ page, baseURL }) => {
  const type = "e2e-type-" + Date.now();
  const before = Date.now();
  expect((await reportEvent(page.request, baseURL!, { type, message: type })).status()).toBe(204);
  const token = await requestToken(page);

  const stats = await getJson(page, token, "/api/1.0/stats");
  expect(stats.byType[type]).toBe(1);
  expect(stats.latestTimestamp).toBeGreaterThanOrEqual(before);
});

test("anonymous clients can report events", async ({ playwright, baseURL }) => {
  // The client script also runs on public pages (e.g. login), so this endpoint is #[PublicPage].
  const anon = await playwright.request.newContext({ baseURL, storageState: ANONYMOUS });
  const response = await reportEvent(anon, baseURL!, { message: "anonymous report " + Date.now() });
  expect(response.status()).toBe(204);
  await anon.dispose();
});

test("anonymous users cannot read events, stats or settings", async ({ playwright, baseURL }) => {
  const anon = await playwright.request.newContext({ baseURL, storageState: ANONYMOUS });
  for (const path of ["/api/1.0/events", "/api/1.0/stats", "/settings"]) {
    const response = await anon.get(`${APP_URL}${path}`, { maxRedirects: 0 });
    expect(response.ok(), `${path} returned ${response.status()}`).toBe(false);
  }
  await anon.dispose();
});

test("anonymous users cannot change settings or purge events", async ({ page, playwright, baseURL }) => {
  const token = await requestToken(page);
  const before = await getJson(page, token, "/settings");

  const anon = await playwright.request.newContext({ baseURL, storageState: ANONYMOUS });
  const save = await anon.post(`${APP_URL}/settings`, {
    form: { retention_hours: before.retention_hours + 1 },
    headers: { Accept: "application/json" },
    maxRedirects: 0,
  });
  expect(save.ok()).toBe(false);
  const purge = await anon.delete(`${APP_URL}/api/1.0/events/purge`, { maxRedirects: 0 });
  expect(purge.ok()).toBe(false);
  await anon.dispose();

  expect(await getJson(page, token, "/settings")).toEqual(before);
});

test("settings API saves values and falls back to 30 days retention", async ({ page }) => {
  const token = await requestToken(page);
  const original: Settings = await getJson(page, token, "/settings");

  try {
    let response = await saveSettings(page, token, {
      ...original, collect_errors: true, collect_unhandled_rejections: false, retention_hours: 48,
    });
    expect(await response.json()).toEqual({ success: true });
    let saved = await getJson(page, token, "/settings");
    expect(saved).toMatchObject({ collect_errors: true, collect_unhandled_rejections: false, retention_hours: 48 });

    response = await saveSettings(page, token, { ...original, retention_hours: 0 });
    expect(response.status()).toBe(200);
    saved = await getJson(page, token, "/settings");
    expect(saved.retention_hours).toBe(720);
  } finally {
    await saveSettings(page, token, original);
  }
  expect(await getJson(page, token, "/settings")).toEqual(original);
});
