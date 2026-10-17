import { test, expect } from "@playwright/test";
import { APP_URL, reportEvent } from "./helpers";

// test("navigation menu leads to event browser", async ({ page }) => {
//   await page.goto("/");
//   const link = page
//     .getByRole("navigation")
//     .getByRole("link", { name: "Frontend Insight" });
//   await expect(link).toBeVisible();
//   await link.click();
//   await page.waitForURL(/\/apps\/frontend_insight/);
//
//   await expect(page.locator("#mwb-fei")).toBeVisible();
// });

test("event browser loads with stats indicators and table", async ({ page }) => {
  await page.goto("/index.php/apps/frontend_insight");

  // Indicators section is present
  // await expect(page.locator(".indicators")).toBeVisible();

  // Table is rendered (or empty-state if no data)
  const app = page.locator("#mwb-fei");
  await expect(
    app.locator("table.mwb-table").or(app.locator(".empty-state"))
  ).toBeVisible();
});

test("event browser shows admin purge button for admin user", async ({ page }) => {
  await page.goto("/index.php/apps/frontend_insight");

  await expect(page.getByRole("button", { name: "Purge all events" })).toBeVisible();
});

test("filter input is present in the event browser", async ({ page }) => {
  await page.goto("/index.php/apps/frontend_insight");

  // The filter is inside a <details> disclosure — open it first
  await page.locator("details.table-controls > summary").click();
  await expect(
    page.locator("#mwb-fei input[type='text']").first()
  ).toBeVisible();
});

test("events are reported and appear in the table", async ({ page, request, baseURL }) => {
  const response = await request.post(
    `${baseURL}/index.php/apps/frontend_insight/report/error`,
    {
      form: {
        timestamp: Date.now(),
        type: "error",
        useragent: "Playwright/e2e-test",
        url: `${baseURL}/index.php/apps/frontend_insight`,
        message: "e2e test error message",
        stack: "Error: e2e test error message\n    at e2e.test:1:1",
        file: "e2e.test.js:1:1",
      },
    }
  );
  expect(response.status()).toBe(204);

  await page.goto("/index.php/apps/frontend_insight");

  // The error message should appear in the col-msg column
  const msgCell = page
    .locator(".col-msg .mwb-copy-text")
    .filter({ hasText: "e2e test error message" });
  await expect(msgCell).toBeVisible({ timeout: 10_000 });
});

test("row expansion shows stack trace and user agent", async ({ page, request, baseURL }) => {
  const message = "expand-test error " + Date.now();
  const response = await request.post(
    `${baseURL}/index.php/apps/frontend_insight/report/error`,
    {
      form: {
        timestamp: Date.now(),
        type: "error",
        useragent: "Playwright/e2e-expand-test",
        url: `${baseURL}/index.php/apps/frontend_insight`,
        message,
        stack: `Error: ${message}\n    at expand:1:1`,
        file: "expand.js:1:1",
      },
    }
  );
  expect(response.status()).toBe(204);

  await page.goto("/index.php/apps/frontend_insight");

  // Find the row containing the message
  const row = page
    .locator("tr")
    .filter({ has: page.locator(".col-msg .mwb-copy-text", { hasText: message }) });
  await expect(row).toBeVisible({ timeout: 10_000 });

  // Double-click to expand
  await row.dblclick();

  // Expanded details row should show stack trace and user agent
  const details = page.locator("tr.details");
  await expect(details).toBeVisible();
  await expect(details.locator("pre.mono")).toContainText(message);
  await expect(details.getByText("Playwright/e2e-expand-test")).toBeVisible();
});

test("filter form narrows the table to matching events", async ({ page, request, baseURL }) => {
  const id = Date.now();
  const wanted = `filter-wanted ${id}`;
  const other = `filter-other ${id}`;
  for (const message of [wanted, other]) {
    expect((await reportEvent(request, baseURL!, { message })).status()).toBe(204);
  }

  await page.goto(APP_URL);
  const cell = (text: string) => page.locator(".col-msg .mwb-copy-text").filter({ hasText: text });
  await expect(cell(other)).toBeVisible({ timeout: 10_000 });

  await page.locator("details.table-controls > summary").click();
  await page.locator("details.table-controls input[type='text']").fill(wanted);
  await page.getByRole("button", { name: "Apply" }).click();

  await expect(cell(wanted)).toBeVisible();
  await expect(cell(other)).toHaveCount(0);
  await expect(page.locator("table.mwb-table tbody tr")).toHaveCount(1);
});

test("filter with no match shows the empty state", async ({ page }) => {
  await page.goto(APP_URL);
  await page.locator("details.table-controls > summary").click();
  await page.locator("details.table-controls input[type='text']").fill("no-such-event-" + Date.now());
  await page.getByRole("button", { name: "Apply" }).click();

  await expect(page.locator(".empty-state")).toBeVisible();
});

test("expander button toggles row details", async ({ page, request, baseURL }) => {
  const message = "expander-test error " + Date.now();
  expect((await reportEvent(request, baseURL!, { message, stack: `Error: ${message}` })).status()).toBe(204);

  await page.goto(APP_URL);
  const row = page
    .locator("tr")
    .filter({ has: page.locator(".col-msg .mwb-copy-text", { hasText: message }) });
  await expect(row).toBeVisible({ timeout: 10_000 });

  await row.getByRole("button", { name: "Show details" }).click();
  await expect(page.locator("tr.details pre.mono")).toContainText(message);

  await row.getByRole("button", { name: "Hide details" }).click();
  await expect(page.locator("tr.details")).toHaveCount(0);
});

test("indicators show per-type counts", async ({ page, request, baseURL }) => {
  const type = "e2e-indicator-" + Date.now();
  for (let i = 0; i < 2; i++) {
    expect((await reportEvent(request, baseURL!, { type, message: `${type} ${i}` })).status()).toBe(204);
  }

  await page.goto(APP_URL);
  const indicator = page.locator(".indicators .indicator").filter({ hasText: `${type}:` });
  await expect(indicator).toContainText("2 events", { timeout: 10_000 });
  await expect(page.locator(".indicators")).toContainText("Latest event");
});

test("purge all events shows confirmation and clears table", async ({ page, request, baseURL }) => {
  const message = "purge-test error " + Date.now();
  const response = await request.post(
    `${baseURL}/index.php/apps/frontend_insight/report/error`,
    {
      form: {
        timestamp: Date.now(),
        type: "error",
        useragent: "Playwright/purge-test",
        url: `${baseURL}/index.php/apps/frontend_insight`,
        message,
      },
    }
  );
  expect(response.status()).toBe(204);

  await page.goto("/index.php/apps/frontend_insight");

  // Wait for the seeded event to show
  const msgCell = page
    .locator(".col-msg .mwb-copy-text")
    .filter({ hasText: message });
  await expect(msgCell).toBeVisible({ timeout: 10_000 });

  // Accept the browser confirm() dialog, then click purge
  page.on("dialog", (dialog) => dialog.accept());
  await page.getByRole("button", { name: "Purge all events" }).click();

  // Table should be empty after purge
  await expect(page.locator(".empty-state")).toBeVisible({ timeout: 10_000 });
});
