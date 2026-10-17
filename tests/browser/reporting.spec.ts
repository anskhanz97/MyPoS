import { expect as baseExpect, test } from "@playwright/test";
import { readFile } from "node:fs/promises";

const expect = baseExpect.configure({ timeout: 20000 });

test.setTimeout(300000);
test.use({ actionTimeout: 30000 });
test.describe.configure({ timeout: 300000 });

test("live ledger reports support filters, pagination, drilldown, saved filters and exports", async ({
    page,
}) => {
    test.skip(
        !process.env.REPORT_ACCEPTANCE_URL,
        "Run against the disposable reporting acceptance server.",
    );
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));
    page.on("console", (message) => {
        if (message.type() === "error") console.error(message.text());
    });
    page.on("requestfailed", (request) =>
        console.error(request.url(), request.failure()?.errorText),
    );
    await page.goto(
        `${process.env.REPORT_ACCEPTANCE_URL}/?report=sales-summary`,
    );
    await expect(
        page.getByRole("heading", { name: "Sign in to view reports" }),
    ).toBeVisible({ timeout: 20000 });
    await page
        .getByLabel("Email", { exact: true })
        .fill("report-owner@example.test");
    await page
        .getByLabel("Password", { exact: true })
        .fill("Report-Acceptance-2026!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Sales summary", exact: true }),
    ).toBeVisible();
    await expect(page.getByRole("table").first()).toContainText("5,950.00");
    const exportMenu = page.getByRole("button", {
        name: "Export",
        exact: true,
    });
    const dateRange = page.getByRole("combobox", {
        name: "Date range",
        exact: true,
    });
    await exportMenu.click();
    await expect(exportMenu).toHaveAttribute("aria-expanded", "true");
    await dateRange.click();
    await expect(exportMenu).toHaveAttribute("aria-expanded", "false");
    await expect(page.locator("[popover]:popover-open")).toHaveCount(1);
    await page.setViewportSize({ width: 1440, height: 1050 });
    await page.screenshot({ path: "output/mypos-dropdown-light.png" });
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await dateRange.click();
    await page.screenshot({ path: "output/mypos-dropdown-dark.png" });
    await page.getByRole("button", { name: "Use light mode" }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await dateRange.click();
    await page.screenshot({ path: "output/mypos-dropdown-mobile.png" });
    await page.keyboard.press("Escape");
    await expect(page.locator("[popover]:popover-open")).toHaveCount(0);
    await expect(dateRange).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 1050 });
    await dateRange.press("ArrowDown");
    await page.keyboard.press("End");
    await expect(
        page.getByRole("option", { name: "Custom range", exact: true }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(dateRange).toHaveText("Custom range");
    await page.getByRole("button", { name: "Profile and logout" }).click();
    await exportMenu.click();
    await expect(page.locator("[popover]:popover-open")).toHaveCount(1);
    await page
        .getByRole("heading", { name: "Sales summary", exact: true })
        .click();
    await expect(page.locator("[popover]:popover-open")).toHaveCount(0);
    const reportUrl = new URL(page.url());
    const reportBase = `${reportUrl.origin}/api/v1/merchants/${reportUrl.searchParams.get("merchant")}/reporting/reports/`;
    const catalogueResponse = await page.request.get(
        reportBase.replace(/reports\/$/, "catalog"),
    );
    expect(catalogueResponse.ok()).toBe(true);
    const catalogue = await catalogueResponse.json();
    for (const report of catalogue.data.reports) {
        const response = await page.request.get(
            `${reportBase}${report.key}?${reportUrl.searchParams}`,
        );
        expect(response.ok(), `${report.key}: ${response.status()}`).toBe(true);
    }
    for (const key of ["sales-by-product", "gross-profit", "profit-loss"]) {
        const response = await page.request.get(
            `${reportBase}${key}?${reportUrl.searchParams}`,
        );
        expect(response.ok()).toBe(true);
        const body = await response.json();
        expect(body.data.summary.revenue).toBe("5950.00");
        expect(body.data.summary.gross_profit).toBe("3570.00");
    }
    await page.getByRole("combobox", { name: "Rows", exact: true }).click();
    await page.getByRole("option", { name: "25", exact: true }).click();
    await expect(page.getByText("31 results · Page 1 of 2")).toBeVisible();
    await page.getByRole("button", { name: "Next", exact: true }).click();
    await expect(page.getByText("31 results · Page 2 of 2")).toBeVisible();
    await page.getByRole("button", { name: "Previous", exact: true }).click();
    await page
        .getByRole("button", { name: "View lines", exact: true })
        .first()
        .click();
    await expect(page.getByRole("dialog")).toContainText("Acceptance Tea");
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Cancel", exact: true })
        .click();
    await page.getByLabel("Saved filter name").fill("Acceptance selection");
    await page
        .getByRole("button", { name: "Save filters", exact: true })
        .click();
    await expect(
        page.getByRole("status").filter({ hasText: "Report filters saved" }),
    ).toBeVisible();
    await page.getByLabel("Search", { exact: true }).fill("not-a-real-product");
    await page
        .getByRole("button", { name: "Generate report", exact: true })
        .click();
    await expect(page.getByText("No records for this selection")).toBeVisible();
    await expect(page.locator(".state-panel--empty svg")).toBeVisible();
    await page
        .locator(".table-panel")
        .screenshot({ path: "output/mypos-report-empty.png" });
    await page
        .getByRole("button", { name: "Acceptance selection", exact: true })
        .click();
    await expect(page.getByText("31 results · Page 1 of 2")).toBeVisible();
    await exportMenu.click();
    const exportResponse = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            response.url().includes("/reports/sales-summary/exports"),
    );
    await page.getByRole("menuitem", { name: "CSV", exact: true }).click();
    const exported = await (await exportResponse).json();
    const downloadLink = page.locator(
        `a[href$="/exports/${exported.data.public_id}/download"]`,
    );
    await expect(downloadLink).toBeVisible();
    const downloadPromise = page.waitForEvent("download", { timeout: 60000 });
    await downloadLink.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(
        /^sales-summary-\d{4}-\d{2}-\d{2}-to-\d{4}-\d{2}-\d{2}\.csv$/,
    );
    const downloadedPath = await download.path();
    expect(downloadedPath).not.toBeNull();
    const csv = await readFile(downloadedPath!, "utf8");
    expect(csv.trim().split(/\r?\n/)).toHaveLength(32);
    expect(csv).toContain("-50.00");
    await expect(exportMenu).toHaveAttribute("aria-expanded", "false");
    await page.setViewportSize({ width: 1440, height: 1050 });
    await page.keyboard.press("Control+Home");
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await page.screenshot({
        path: "output/mypos-reports-live-dark.png",
        fullPage: false,
    });
    await page.getByRole("button", { name: "Use light mode" }).click();
    await page.screenshot({
        path: "output/mypos-reports-live-light.png",
        fullPage: false,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: "output/mypos-reports-live-mobile.png",
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    expect(errors).toEqual([]);
});
