import { expect, test } from "@playwright/test";

test.beforeEach(async ({ page }) => {
    await page.route("**/api/v1/**", async (route) => {
        const path = new URL(route.request().url()).pathname;
        let data: unknown = [];
        if (path === "/api/v1/merchants")
            data = [{ id: "calendar-test", name: "Calendar test store" }];
        else if (path.endsWith("/catalog"))
            data = {
                reports: [
                    {
                        key: "sales-summary",
                        title: "Sales summary",
                        group: "Sales",
                        type: "sales",
                        sensitive: false,
                    },
                ],
                stores: [],
                role: "owner",
                can_consolidate: true,
                lookups: {},
            };
        else if (path.includes("/reports/"))
            data = {
                report: "sales-summary",
                title: "Sales summary",
                columns: [],
                rows: [],
                summary: {},
                definitions: [],
                limitations: [],
                period: {
                    start: "2026-10-01",
                    end: "2026-10-18",
                    timezone: "Asia/Karachi",
                    basis: "Sale date",
                },
                freshness: { generated_at: "2026-10-18T12:00:00Z" },
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                    per_page: 50,
                },
            };
        await route.fulfill({ json: { data } });
    });
    await page.goto("/?report=sales-summary&start=2026-10-01&end=2026-10-18");
    await expect(
        page.getByRole("heading", { name: "Sales summary", exact: true }),
    ).toBeVisible();
});

test("calendars share dropdown ownership, theme and responsive placement", async ({
    page,
}) => {
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    const fromCalendar = page.getByRole("button", {
        name: "From calendar",
        exact: true,
    });
    const toCalendar = page.getByRole("button", {
        name: "To calendar",
        exact: true,
    });
    const exportMenu = page.getByRole("button", {
        name: "Export",
        exact: true,
    });
    await exportMenu.click();
    await fromCalendar.click();
    await expect(exportMenu).toHaveAttribute("aria-expanded", "false");
    const calendar = page.getByRole("dialog", { name: "From calendar" });
    await expect(calendar).toBeVisible();
    await expect(page.locator("[popover]:popover-open")).toHaveCount(1);
    await expect(
        calendar.getByRole("button", {
            name: "Thursday, 1 October 2026",
            exact: true,
        }),
    ).toBeFocused();
    await page.screenshot({ path: "output/mypos-calendar-light.png" });
    await calendar.getByRole("button", { name: "Next month" }).click();
    await expect(calendar.getByRole("grid")).toHaveAccessibleName(
        "November 2026",
    );
    await expect(calendar).toBeVisible();
    await toCalendar.click();
    await expect(fromCalendar).toHaveAttribute("aria-expanded", "false");
    await expect(page.locator("[popover]:popover-open")).toHaveCount(1);
    await page.keyboard.press("Escape");
    await expect(toCalendar).toBeFocused();
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await fromCalendar.click();
    await page.screenshot({ path: "output/mypos-calendar-dark.png" });
    const selected = calendar.getByRole("button", {
        name: "Thursday, 1 October 2026",
        exact: true,
    });
    await expect(selected).toHaveCSS("color", "rgb(33, 17, 9)");
    await page.keyboard.press("Escape");
    await page.getByRole("button", { name: "Use light mode" }).click();
    await page.addStyleTag({ content: "html { scrollbar-gutter: stable; }" });
    await page.setViewportSize({ width: 390, height: 844 });
    await fromCalendar.click();
    const bounds = await calendar.boundingBox();
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    const visibleWidth = await page.evaluate(
        () => document.documentElement.clientWidth,
    );
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(visibleWidth - 10);
    await page.screenshot({ path: "output/mypos-calendar-mobile.png" });
    await calendar.getByRole("combobox", { name: "Year", exact: true }).click();
    const yearBounds = await page.getByRole("listbox").boundingBox();
    expect(yearBounds!.x).toBeGreaterThanOrEqual(12);
    expect(yearBounds!.x + yearBounds!.width).toBeLessThanOrEqual(
        visibleWidth - 10,
    );
    await page.screenshot({ path: "output/mypos-calendar-year-mobile.png" });
    await page.keyboard.press("Escape");
    await page
        .getByRole("heading", { name: "Sales summary", exact: true })
        .click();
    await expect(page.locator("[popover]:popover-open")).toHaveCount(0);
    expect(errors).toEqual([]);
});

test("month and year dropdowns jump directly while preserving calendar limits and ownership", async ({
    page,
}) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page
        .getByRole("button", { name: "From calendar", exact: true })
        .click();
    const calendar = page.getByRole("dialog", { name: "From calendar" });
    const year = calendar.getByRole("combobox", { name: "Year", exact: true });
    const month = calendar.getByRole("combobox", {
        name: "Month",
        exact: true,
    });
    await year.click();
    await expect(calendar).toBeVisible();
    await page.screenshot({ path: "output/mypos-calendar-year.png" });
    await page.getByRole("option", { name: "2000", exact: true }).click();
    await expect(calendar.getByRole("grid")).toHaveAccessibleName(
        "October 2000",
    );
    await month.click();
    await page.getByRole("option", { name: "February", exact: true }).click();
    await expect(calendar.getByRole("grid")).toHaveAccessibleName(
        "February 2000",
    );
    await expect(page.getByLabel("From", { exact: true })).toHaveValue(
        "2026-10-01",
    );
    await year.click();
    await month.click();
    await expect(year).toHaveAttribute("aria-expanded", "false");
    await expect(month).toHaveAttribute("aria-expanded", "true");
    await page.keyboard.press("Escape");
    await expect(calendar).toBeVisible();
    await expect(month).toBeFocused();
    await calendar
        .getByRole("button", { name: "Tuesday, 29 February 2000", exact: true })
        .click();
    await expect(page.getByLabel("From", { exact: true })).toHaveValue(
        "2000-02-29",
    );
    await page
        .getByRole("button", { name: "To calendar", exact: true })
        .click();
    const toCalendar = page.getByRole("dialog", { name: "To calendar" });
    await toCalendar
        .getByRole("combobox", { name: "Year", exact: true })
        .click();
    await expect(
        page.getByRole("option", { name: "1999", exact: true }),
    ).toHaveCount(0);
    await page.getByRole("option", { name: "2000", exact: true }).click();
    await toCalendar
        .getByRole("combobox", { name: "Month", exact: true })
        .click();
    await expect(
        page.getByRole("option", { name: "January", exact: true }),
    ).toBeDisabled();
    await page.getByRole("button", { name: "Export", exact: true }).click();
    await expect(toCalendar).not.toBeVisible();
    await expect(page.locator("[popover]:popover-open")).toHaveCount(1);
});

test("calendar selection, date limits, typed dates and keyboard month boundaries stay correct", async ({
    page,
}) => {
    const from = page.getByLabel("From", { exact: true });
    const to = page.getByLabel("To", { exact: true });
    await from.fill("2024-02-28");
    await to.fill("2024-03-01");
    const fromCalendar = page.getByRole("button", {
        name: "From calendar",
        exact: true,
    });
    await fromCalendar.focus();
    await fromCalendar.press("ArrowDown");
    await page.keyboard.press("ArrowRight");
    await expect(
        page.getByRole("button", {
            name: "Thursday, 29 February 2024",
            exact: true,
        }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(from).toHaveValue("2024-02-29");
    await expect(
        page.getByRole("combobox", { name: "Date range", exact: true }),
    ).toHaveText("Custom range");
    await expect(page.locator("[popover]:popover-open")).toHaveCount(0);
    await page
        .getByRole("button", { name: "To calendar", exact: true })
        .click();
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press("ArrowLeft");
    await expect(
        page.getByRole("button", {
            name: "Thursday, 29 February 2024",
            exact: true,
        }),
    ).toBeFocused();
    await expect(
        page.getByRole("button", {
            name: "Wednesday, 28 February 2024",
            exact: true,
        }),
    ).toBeDisabled();
    await page.keyboard.press("Enter");
    await expect(to).toHaveValue("2024-02-29");
    await from.fill("2024-01-31");
    await from.press("Alt+ArrowDown");
    await page.keyboard.press("PageDown");
    await expect(
        page.getByRole("button", {
            name: "Thursday, 29 February 2024",
            exact: true,
        }),
    ).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.locator("[popover]:popover-open")).toHaveCount(0);
    await from.fill("2024-12-31");
    await from.click();
    await expect(
        page.getByRole("dialog", { name: "From calendar" }),
    ).toBeVisible();
    await page.keyboard.press("ArrowRight");
    await expect(
        page.getByRole("button", {
            name: "Wednesday, 1 January 2025",
            exact: true,
        }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(from).toHaveValue("2025-01-01");
    await page.emulateMedia({ reducedMotion: "reduce" });
    await fromCalendar.click();
    await expect(page.getByRole("dialog", { name: "From calendar" })).toHaveCSS(
        "animation-name",
        "none",
    );
});
