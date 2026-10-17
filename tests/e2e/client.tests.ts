import { test, expect, type Page } from "@playwright/test";
import { getJson, requestToken, saveSettings, type Settings } from "./helpers";

type StoredEvent = { type: string; message: string; stack: string | null };

function findEvents(page: Page, token: string, q: string): Promise<StoredEvent[]> {
  return getJson(page, token, `/api/1.0/events?q=${encodeURIComponent(q)}`).then((d) => d.values);
}

function throwUncaught(page: Page, message: string) {
  return page.evaluate((m) => { setTimeout(() => { throw new Error(m); }); }, message);
}

function rejectUnhandled(page: Page, message: string) {
  return page.evaluate((m) => { Promise.reject(new Error(m)); }, message);
}

test("client script reports uncaught errors", async ({ page }) => {
  const token = await requestToken(page);
  const message = "client-uncaught " + Date.now();
  await throwUncaught(page, message);

  await expect.poll(async () => (await findEvents(page, token, message)).map((e) => e.type)).toEqual(["error"]);
});

test("client script reports unhandled promise rejections with their stack", async ({ page }) => {
  const token = await requestToken(page);
  const message = "client-rejection " + Date.now();
  await rejectUnhandled(page, message);

  await expect.poll(async () => (await findEvents(page, token, message)).map((e) => e.type)).toEqual(["unhandledrejection"]);
  const [event] = await findEvents(page, token, message);
  expect(event.message).toBe(message);
  expect(event.stack).toContain(message);
});

test("client script stops reporting errors when collection is disabled", async ({ page }) => {
  const token = await requestToken(page);
  const original: Settings = await getJson(page, token, "/settings");
  const id = Date.now();

  try {
    await saveSettings(page, token, { ...original, collect_errors: false, collect_unhandled_rejections: true });
    await page.reload(); // the client reads the settings from initial state at page load

    await throwUncaught(page, `disabled-error ${id}`);
    await page.waitForTimeout(1_000);
    // The rejection is still collected; once it arrives, a reported error would have arrived too
    await rejectUnhandled(page, `disabled-marker ${id}`);
    await expect.poll(async () => (await findEvents(page, token, `disabled-marker ${id}`)).length).toBe(1);

    expect(await findEvents(page, token, `disabled-error ${id}`)).toEqual([]);
  } finally {
    await saveSettings(page, token, original);
  }
});
