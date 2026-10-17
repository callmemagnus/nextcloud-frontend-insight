import { expect, type APIRequestContext, type Browser, type Page, type PlaywrightWorkerArgs } from "@playwright/test";

export function authFileFromUrl(url: string) {
    const s = url.split(':')
    return `.playwright/auth/user-${s[2]}.json`
}

export const APP_URL = "/index.php/apps/frontend_insight";
export const ANONYMOUS = { cookies: [], origins: [] };

export type Settings = {
    collect_errors: boolean;
    collect_unhandled_rejections: boolean;
    retention_hours: number;
    allowed_groups: string[];
};

export function reportEvent(
    request: APIRequestContext,
    baseURL: string,
    overrides: Record<string, string | number>
) {
    return request.post(`${baseURL}${APP_URL}/report/error`, {
        form: {
            timestamp: Date.now(),
            type: "error",
            useragent: "Playwright/e2e-test",
            url: `${baseURL}${APP_URL}`,
            ...overrides,
        },
    });
}

// Same CSRF token @nextcloud/axios sends; without it the #[CORS] endpoints log the session out.
export async function requestToken(page: Page, path = APP_URL): Promise<string> {
    await page.goto(path);
    return page.evaluate(() => document.head.dataset.requesttoken ?? "");
}

export async function getJson(page: Page, token: string, path: string) {
    const response = await page.request.get(`${APP_URL}${path}`, {
        headers: { requesttoken: token, Accept: "application/json" },
    });
    expect(response.status(), `GET ${path}`).toBe(200);
    return response.json();
}

// Checkboxes are posted only when checked, like the HTML form does
export function settingsForm(s: Settings): Record<string, string | number> {
    return {
        ...(s.collect_errors ? { collect_errors: "1" } : {}),
        ...(s.collect_unhandled_rejections ? { collect_unhandled_rejections: "1" } : {}),
        retention_hours: s.retention_hours,
        allowed_groups: s.allowed_groups.join(","),
    };
}

export function saveSettings(page: Page, token: string, s: Settings) {
    return page.request.post(`${APP_URL}/settings`, {
        form: settingsForm(s),
        headers: { requesttoken: token, Accept: "application/json" },
    });
}

// Basic-auth admin client for OCS calls, separate from the browser session
export function adminOcs(playwright: PlaywrightWorkerArgs["playwright"], baseURL: string) {
    return playwright.request.newContext({
        baseURL,
        storageState: ANONYMOUS,
        httpCredentials: { username: "admin", password: "admin", send: "always" },
        extraHTTPHeaders: { "OCS-APIRequest": "true", Accept: "application/json" },
    });
}

export async function loginAs(browser: Browser, baseURL: string, user: string, password: string) {
    const context = await browser.newContext({ baseURL, storageState: ANONYMOUS });
    const page = await context.newPage();
    await page.goto("/");
    await page.locator("#user").fill(user);
    await page.locator("#password").fill(password);
    await page.locator("button[type=submit]").click();
    await page.waitForURL("/index.php/apps/dashboard/");
    return page;
}
