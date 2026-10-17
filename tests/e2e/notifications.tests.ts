import { test, expect, type APIRequestContext } from "@playwright/test";
import { ANONYMOUS, APP_URL, adminOcs, reportEvent, requestToken } from "./helpers";

async function pendingNotifications(ocs: APIRequestContext) {
  const response = await ocs.get("/ocs/v2.php/apps/notifications/api/v2/notifications");
  expect(response.ok(), "notifications app must be enabled").toBe(true);
  const { ocs: body } = await response.json();
  return (body.data as { app: string }[]).filter((n) => n.app === "frontend_insight").length;
}

test("first event after an empty log notifies admins, opening it from the notification dismisses it", async ({ page, playwright, baseURL }) => {
  const ocs = await adminOcs(playwright, baseURL!);

  // Clear notifications left by earlier tests, then start from an empty event log
  await page.goto(`${APP_URL}?from=notification`);
  await expect.poll(() => pendingNotifications(ocs)).toBe(0);
  const token = await requestToken(page);
  const purge = await page.request.delete(`${APP_URL}/api/1.0/events/purge`, { headers: { requesttoken: token } });
  expect(purge.ok()).toBe(true);

  const anon = await playwright.request.newContext({ baseURL, storageState: ANONYMOUS });
  expect((await reportEvent(anon, baseURL!, { message: "notification trigger" })).status()).toBe(204);
  await anon.dispose();
  await expect.poll(() => pendingNotifications(ocs)).toBeGreaterThan(0);

  await page.goto(`${APP_URL}?from=notification`);
  await expect.poll(() => pendingNotifications(ocs)).toBe(0);
  await ocs.dispose();
});

test("further events do not notify again while the log is not empty", async ({ page, playwright, baseURL, request }) => {
  const ocs = await adminOcs(playwright, baseURL!);

  expect((await reportEvent(request, baseURL!, { message: "notification seed" })).status()).toBe(204);
  await page.goto(`${APP_URL}?from=notification`);
  await expect.poll(() => pendingNotifications(ocs)).toBe(0);

  expect((await reportEvent(request, baseURL!, { message: "notification no-repeat" })).status()).toBe(204);
  expect(await pendingNotifications(ocs)).toBe(0);
  await ocs.dispose();
});
