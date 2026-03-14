import { test, expect } from "@playwright/test";

test("admin settings page is accessible", async ({ page }) => {
  await page.goto("/index.php/settings/admin/frontend_insight");

  // The settings section heading should be visible
  await expect(
    page.getByRole("heading", { name: /front-end insight settings/i })
  ).toBeVisible();
});

test("admin settings page shows all config fields", async ({ page }) => {
  await page.goto("/index.php/settings/admin/frontend_insight");

  // Error collection labels (Nextcloud hides the checkbox visually, test the label)
  await expect(
    page.locator("label[for='fei-collect-errors']")
  ).toBeVisible();
  await expect(
    page.locator("label[for='fei-collect-unhandled']")
  ).toBeVisible();

  // Retention hours input
  await expect(page.locator("#fei-retention")).toBeVisible();

  // Save button
  await expect(
    page.getByRole("button", { name: /save/i })
  ).toBeVisible();
});

test("admin settings can be saved", async ({ page }) => {
  await page.goto("/index.php/settings/admin/frontend_insight");

  // Nextcloud hides the actual checkbox visually — must click the label to toggle
  const collectErrorsCheckbox = page.locator("#fei-collect-errors");
  const collectErrorsLabel = page.locator("label[for='fei-collect-errors']");
  const wasChecked = await collectErrorsCheckbox.isChecked();
  await collectErrorsLabel.click();
  await expect(collectErrorsCheckbox).toBeChecked({ checked: !wasChecked });

  // Set a retention value
  const retentionInput = page.locator("#fei-retention");
  await retentionInput.fill("48");

  await page.getByRole("button", { name: /save settings/i }).click();

  // Confirmation that settings were saved
  await expect(
    page.locator(".msg.success")
  ).toBeVisible({ timeout: 5_000 });

  // Restore original state
  const nowChecked = await collectErrorsCheckbox.isChecked();
  if (nowChecked !== wasChecked) {
    await collectErrorsLabel.click();
  }
  await retentionInput.fill("720");
  await page.getByRole("button", { name: /save settings/i }).click();
});
