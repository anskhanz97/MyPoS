import { expect, test, type Page } from "@playwright/test";

async function openManagementSection(
    page: Page,
    group: string,
    section: string,
): Promise<void> {
    await page
        .getByRole("button", { name: `Open ${group} navigation` })
        .click();
    await page
        .getByRole("navigation", { name: `${group} pages` })
        .getByRole("button", { name: section, exact: true })
        .click();
}

test("shell persists theme without losing populated form state or focus", async ({
    page,
}) => {
    await page.goto("/");
    await expect(
        page.getByRole("heading", { name: "Fast controls, clear state." }),
    ).toBeVisible();
    const customer = page.getByLabel("Customer name");
    await customer.fill("Synthetic retained value");
    const toggle = page.getByRole("button", { name: "Use dark mode" });
    await expect(toggle).toHaveAttribute("aria-pressed", "false");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-management-light.png",
            fullPage: true,
        });
    }

    await toggle.focus();
    await page.keyboard.press("Enter");
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(customer).toHaveValue("Synthetic retained value");
    const lightToggle = page.getByRole("button", { name: "Use light mode" });
    await expect(lightToggle).toBeFocused();
    await expect(lightToggle).toHaveAttribute("aria-pressed", "true");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-management-dark.png",
            fullPage: true,
        });
    }

    await page.reload();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
});

test("staff and public theme preferences are independent", async ({ page }) => {
    await page.goto("/");
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await page.getByRole("button", { name: "public" }).click();

    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await expect(page.getByText("Member preview")).toBeVisible();
    await expect(page.getByRole("button", { name: "Void draft" })).toHaveCount(
        0,
    );
    await expect(
        page.getByRole("heading", { name: "Member access, limited data." }),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-public-light.png",
            fullPage: true,
        });
    }

    await page.getByRole("button", { name: "management" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await page.getByRole("button", { name: "checkout" }).click();
    await expect(page.getByText("Checkout workspace")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-checkout-dark.png",
            fullPage: true,
        });
    }
});

test("narrow shell has no horizontal page overflow and print stays black on white", async ({
    page,
}) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto("/");
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-management-narrow.png",
            fullPage: true,
        });
    }

    await page.emulateMedia({ media: "print" });
    const printColors = await page.evaluate(() => {
        const style = getComputedStyle(document.body);
        return { background: style.backgroundColor, color: style.color };
    });
    expect(printColors).toEqual({
        background: "rgb(255, 255, 255)",
        color: "rgb(0, 0, 0)",
    });
    await expect(page.getByRole("button", { name: /mode/ })).toBeHidden();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-print.png",
            fullPage: true,
        });
    }
});

test("semantic text pairs meet WCAG AA contrast in both themes", async ({
    page,
}) => {
    await page.goto("/");
    for (const theme of ["light", "dark"] as const) {
        if ((await page.locator("html").getAttribute("data-theme")) !== theme) {
            await page
                .getByRole("button", { name: `Use ${theme} mode` })
                .click();
        }
        const ratios = await page.evaluate(() => {
            function luminance(color: string): number {
                const values =
                    color
                        .match(/[\d.]+/g)
                        ?.slice(0, 3)
                        .map(Number) ?? [];
                const [r = 0, g = 0, b = 0] = values.map((value) => {
                    const channel = value / 255;
                    return channel <= 0.04045
                        ? channel / 12.92
                        : ((channel + 0.055) / 1.055) ** 2.4;
                });
                return 0.2126 * r + 0.7152 * g + 0.0722 * b;
            }
            function ratio(foreground: string, background: string): number {
                const [light, dark] = [
                    luminance(foreground),
                    luminance(background),
                ].sort((a, b) => b - a);
                return (light + 0.05) / (dark + 0.05);
            }
            const body = getComputedStyle(document.body);
            const paragraph = getComputedStyle(
                document.querySelector(".page-heading p:last-child")!,
            );
            const surface = getComputedStyle(
                document.querySelector(".page-heading")!,
            );
            return [
                ratio(body.color, body.backgroundColor),
                ratio(paragraph.color, surface.backgroundColor),
            ];
        });
        for (const ratio of ratios) expect(ratio).toBeGreaterThanOrEqual(4.5);
    }
});

test("sidebar navigation collapses and the approval dialog supports keyboard interaction", async ({
    page,
}) => {
    await page.goto("/");
    await expect(page.getByText("Workspace and system state")).toBeVisible();
    await page.getByRole("button", { name: "Collapse navigation" }).click();
    await expect(page.getByText("Workspace and system state")).toBeHidden();
    await page
        .getByRole("button", { name: "Open Overview navigation" })
        .click();
    await expect(page.getByText("Workspace and system state")).toBeVisible();
    await openManagementSection(page, "Overview", "States");
    await expect(
        page.getByRole("heading", { name: "System states" }),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-states-light.png",
            fullPage: true,
        });
    }

    await page.getByRole("button", { name: "Use dark mode" }).click();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t006-states-dark.png",
            fullPage: true,
        });
    }

    const opener = page.getByRole("button", { name: "Open approval prompt" });
    await opener.click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.getByRole("dialog")).toBeHidden();
    await expect(opener).toBeFocused();
});

test("cash control keeps the count blind, records handover, and reveals variance after submit", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Sell", "Cash control");
    await expect(
        page.getByRole("heading", {
            name: "Count what is there. Then compare.",
        }),
    ).toBeVisible();
    await expect(page.getByText("Hidden until count")).toBeVisible();
    await page.getByRole("button", { name: "Handover custody" }).click();
    await expect(page.getByText(/current custodian Bilal Ahmed/)).toBeVisible();
    await page.getByLabel("Counted cash").fill("10280.00");
    await page.getByRole("button", { name: "Submit blind count" }).click();
    await expect(page.locator(".review-box")).toContainText("PKR 250.00");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t020-cash-session-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Approve and close" }).click();
    await expect(page.getByText("closed", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t020-cash-session-dark.png",
            fullPage: true,
        });
    }
});

test("return workflow preserves original allocations and exposes approval and pending refund states", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Sell", "Returns");
    await expect(
        page.getByRole("heading", { name: "Start with the original sale." }),
    ).toBeVisible();
    await expect(page.getByText("Original tax")).toBeVisible();
    await page.getByRole("button", { name: "Preview late case" }).click();
    await expect(
        page.getByRole("button", { name: "Approve this return" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Approve this return" }).click();
    await page.getByLabel("Refund method").selectOption("external_card");
    await page.getByLabel("Actual confirmation reference").fill("PENDING");
    await page.getByRole("button", { name: "Post return" }).click();
    await expect(page.getByText("External action pending")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t026-returns-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t026-returns-dark.png",
            fullPage: true,
        });
    }
});

test("tenant setup reviews and locks ownership while store switching clears transient state", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Setup", "Tenancy");
    await page.getByLabel("Independent records").check();
    await page.getByRole("button", { name: "Continue to review" }).click();
    await page.getByRole("button", { name: "Activate and lock mode" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "cannot be changed after activation",
    );
    await page
        .getByLabel(
            "I reviewed the ownership rules and want to activate this merchant.",
        )
        .check();
    await page.getByRole("button", { name: "Activate and lock mode" }).click();
    await expect(page.getByText("Activated · selection locked")).toBeVisible();

    const draft = page.getByLabel("Transient sale reference");
    await draft.fill("SALE-77");
    await page.getByRole("button", { name: /Islamabad Blue Area/ }).click();
    await expect(draft).toHaveValue("");
    await expect(page.getByText(/Store-specific drafts/)).toBeVisible();
    await expect(
        page.locator('[aria-labelledby="stores-title"] .status-badge--info'),
    ).toHaveText("ISB-01");

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t007-tenancy-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t007-tenancy-dark.png",
            fullPage: true,
        });
    }
});

test("access preview preserves cart across staff switches and consumes one approval", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Setup", "Access");
    await page.getByRole("button", { name: "Sign in securely" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "at least 12 characters",
    );
    await page.getByLabel("Password").fill("Valid-preview-password");
    await page.getByRole("button", { name: "Sign in securely" }).click();
    await expect(page.getByText("Account session active")).toBeVisible();

    const cart = page.getByText("CART-1042 · 3 items · PKR 2,190.00");
    await page
        .getByRole("button", { name: /manager Store operations/i })
        .click();
    await expect(cart).toBeVisible();
    await expect(
        page.getByLabel("Authorized navigation").getByText("Payroll"),
    ).toHaveCount(0);
    await page
        .getByRole("button", { name: /owner Merchant administration/i })
        .click();
    await expect(
        page.getByLabel("Authorized navigation").getByText("Payroll"),
    ).toBeVisible();
    await expect(cart).toBeVisible();

    await page
        .getByRole("button", { name: "Approve this action once" })
        .click();
    await expect(page.getByRole("alert")).toContainText("Enter a reason");
    await page
        .getByLabel("Reason", { exact: true })
        .fill("Manager verified drawer check");
    await page
        .getByRole("button", { name: "Approve this action once" })
        .click();
    await expect(
        page.getByRole("button", { name: "Approval already used" }),
    ).toBeDisabled();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t008-access-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t008-access-dark.png",
            fullPage: true,
        });
    }
});

test("platform subscription preview preserves recovery access through restriction and both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Setup", "Platform");
    await expect(
        page.getByRole("heading", {
            name: "Renew service without mixing billing and sales.",
        }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Restrict new operations" }).click();
    await expect(page.getByText("New selling restricted.")).toBeVisible();
    await expect(
        page.getByLabel("Capabilities retained during restriction"),
    ).toContainText("Submit pending offline sales");
    await page.getByRole("button", { name: "Record and renew" }).click();
    await expect(
        page.getByText("Service renewed through 29 Oct 2026."),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Payment already recorded" }),
    ).toBeDisabled();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t009-platform-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t009-platform-dark.png",
            fullPage: true,
        });
    }
});

test("register provisioning shows one-time pairing, tab ownership, versioned settings and both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Setup", "Registers");
    await expect(
        page.getByRole("heading", { name: "Know which counter can sell." }),
    ).toBeVisible();
    await expect(page.getByText("Unconfigured").first()).toBeVisible();
    await page.getByRole("button", { name: "Issue pairing code" }).click();
    await expect(page.getByText("7KQ4N2PX")).toBeVisible();
    await page
        .getByRole("button", { name: "Complete simulated pairing" })
        .click();
    await expect(page.getByText("Paired", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: /Tab 6C11/ }).click();
    await expect(page.getByText("Read-only tab")).toBeVisible();
    await page.getByLabel("Advanced counter promotions").check();
    await page.getByRole("button", { name: "Publish next version" }).click();
    await expect(page.getByText("Store settings v5 published")).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t010-registers-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t010-registers-dark.png",
            fullPage: true,
        });
    }
});

test("catalog preview searches structures, preserves archive context and renders both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Catalog");
    await expect(
        page.getByRole("heading", {
            name: "One scan, an exact sellable item.",
        }),
    ).toBeVisible();
    const search = page.getByLabel("Search name, SKU or barcode");
    await search.fill("NUT-014");
    await expect(
        page.getByRole("button", { name: /Loose almonds/ }),
    ).toBeVisible();
    await expect(page.getByRole("button", { name: /Green tea/ })).toHaveCount(
        0,
    );
    await search.fill("");
    await page.getByRole("button", { name: /Tea gift set/ }).click();
    await expect(
        page.getByRole("heading", {
            name: "Tea gift set",
            exact: true,
            level: 2,
        }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Archive product" }).click();
    await expect(page.getByText("Archived", { exact: true })).toBeVisible();
    await expect(page.getByText("1 carton = 24 pieces")).toBeVisible();
    await expect(page.getByText(/resolves item reference 00123/)).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t011-catalog-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t011-catalog-dark.png",
            fullPage: true,
        });
    }
});

test("pricing preview switches modes, explains quote allocations and renders both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Pricing");
    await expect(
        page.getByRole("heading", { name: "Every discount explains itself." }),
    ).toBeVisible();
    await expect(page.getByText("m7-s2 · PKR")).toBeVisible();
    await page.getByRole("button", { name: /Manual discounts/ }).click();
    await expect(
        page.getByText("Automatic rules retained, not applied"),
    ).toBeVisible();
    await page.getByRole("button", { name: /Counter promotions/ }).click();
    await expect(page.getByText("Buy 2 tea, get snack")).toBeVisible();
    await page.getByRole("button", { name: "Publish version" }).click();
    await expect(page.getByRole("button", { name: "Published" })).toBeVisible();
    await expect(page.getByText("PKR 2,118.10")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t012-pricing-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t012-pricing-dark.png",
            fullPage: true,
        });
    }
});

test("customer preview searches normalized contacts, validates capture and renders both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Customers", "Customers");
    await expect(
        page.getByRole("heading", {
            name: "Recognize the customer. Keep the sale optional.",
        }),
    ).toBeVisible();
    await page
        .getByLabel("Search name, phone, email or reference")
        .fill("0300 1234567");
    await expect(
        page.getByRole("button", { name: /Ayesha Khan/ }),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: /Bilal Traders/ }),
    ).toHaveCount(0);
    await page.getByRole("button", { name: "New customer" }).click();
    await page.getByRole("button", { name: "Review duplicate" }).click();
    await expect(page.getByText("Enter a customer name.")).toBeVisible();
    await page.getByLabel("Customer name (required)").fill("Sana Ahmed");
    await page.getByRole("button", { name: "Review duplicate" }).click();
    await expect(page.getByRole("status")).toContainText(
        "ready for duplicate review",
    );
    await expect(
        page.getByText("These choices are stored independently."),
    ).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t013-customers-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t013-customers-dark.png",
            fullPage: true,
        });
    }
});

test("inventory preview exposes negative stock, append-only sources, reconciliation and both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Inventory");
    const inventory = page.locator(".inventory-preview");
    await expect(
        inventory.getByRole("heading", {
            name: "Every stock change leaves a trace.",
        }),
    ).toBeVisible();
    await expect(
        inventory.getByText("Warning permitted; review required"),
    ).toBeVisible();
    const stockOnHand = inventory.getByRole("region", {
        name: "Stock on hand",
    });
    await stockOnHand.getByLabel("Search product or SKU").fill("NUT-014");
    await expect(stockOnHand.getByText("Loose almonds")).toBeVisible();
    await expect(stockOnHand.getByText("Green tea 250g")).toHaveCount(0);
    await stockOnHand.getByRole("button", { name: "Clear filters" }).click();
    await expect(inventory.getByText("SALE-887 / LINE-2")).toBeVisible();
    await inventory
        .getByRole("button", { name: "Confirm and rebuild projection" })
        .click();
    await expect(
        inventory.getByText("Projection matches ledger"),
    ).toBeVisible();
    await inventory.getByRole("button", { name: "New adjustment" }).click();
    await inventory.getByRole("button", { name: "Post adjustment" }).click();
    await expect(
        inventory.getByText("Enter a non-zero quantity."),
    ).toBeVisible();
    await inventory.getByLabel("Signed quantity").fill("2");
    await inventory.getByLabel("Reason details").fill("Verified shelf count");
    await inventory.getByRole("button", { name: "Post adjustment" }).click();
    await expect(inventory.getByRole("status")).toContainText(
        "Adjustment posted once",
    );
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t014-inventory-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t014-inventory-dark.png",
            fullPage: true,
        });
    }
});

test("batch preview distinguishes expiry warnings from blocks and proposes FEFO in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Inventory");
    const inventory = page.locator(".inventory-preview");
    await expect(
        inventory.getByRole("heading", {
            name: "Sell the earliest safe stock first.",
        }),
    ).toBeVisible();
    await expect(inventory.getByText("1 expired batch blocked")).toBeVisible();
    await expect(inventory.getByText("Unavailable for sale")).toBeVisible();
    await expect(
        inventory.getByText("Expiring soon", { exact: true }),
    ).toBeVisible();
    await inventory.getByLabel("Batch quantity").fill("7");
    await inventory.getByLabel("Selection strategy").selectOption("fefo");
    await inventory
        .getByRole("button", { name: "Propose safe batches" })
        .click();
    await expect(inventory.getByRole("status")).toContainText(
        "FEFO proposal ready",
    );
    await expect(inventory.getByRole("status")).toContainText(
        "stock not reserved",
    );
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t015-batches-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t015-batches-dark.png",
            fullPage: true,
        });
    }
});

test("transfer and count preview resolves transit and preserves the live movement timeline in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Transfers & counts");
    const preview = page.locator(".transfer-count-preview");
    await expect(
        preview.getByRole("heading", {
            name: "Move stock without losing the trail.",
        }),
    ).toBeVisible();
    await expect(
        preview.getByText("Partially received", { exact: true }),
    ).toBeVisible();
    await preview
        .getByRole("button", { name: "Receive 3 and close short" })
        .click();
    await expect(preview.getByText("Received · short resolved")).toBeVisible();
    await expect(preview.getByText("0.000000 ctn")).toBeVisible();

    await preview.getByRole("button", { name: "Send to review" }).click();
    await expect(preview.getByText("review", { exact: true })).toBeVisible();
    await preview
        .getByRole("button", { name: "Post count adjustment" })
        .click();
    await expect(preview.getByText("posted", { exact: true })).toBeVisible();
    await expect(
        preview.getByText("It does not replace the live balance with 47."),
    ).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t016-transfers-counts-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t016-transfers-counts-dark.png",
            fullPage: true,
        });
    }
});

test("purchasing preview separates planning from receipts and renders supplier history in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Purchasing");
    const preview = page.locator(".purchasing-preview");
    await expect(
        preview.getByRole("heading", {
            name: "Plan the order. Receive it separately.",
        }),
    ).toBeVisible();
    await expect(
        preview.getByText("Supplier balance: PKR 1500.00"),
    ).toBeVisible();
    await preview.getByRole("button", { name: "Archive supplier" }).click();
    await expect(preview.getByText("Archived", { exact: true })).toBeVisible();
    await preview.getByRole("button", { name: "Issue purchase order" }).click();
    await expect(preview.getByText("issued", { exact: true })).toBeVisible();
    await expect(preview.getByRole("status")).toContainText(
        "No stock movement was posted",
    );
    await preview.getByRole("button", { name: "Prepare PDF" }).click();
    await expect(preview.getByRole("status")).toContainText("light styling");

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t017-purchasing-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t017-purchasing-dark.png",
            fullPage: true,
        });
    }
});

test("receiving preview exposes atomic partial receipt and moving-average evidence in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Purchasing");
    const preview = page.locator(".purchasing-preview");
    const workbench = preview.locator(".receipt-workbench");
    await expect(
        workbench.getByRole("heading", { name: "Receive against PO-29A7C1" }),
    ).toBeVisible();
    await expect(
        workbench.getByRole("button", { name: "Post partial receipt" }),
    ).toBeDisabled();
    await preview.getByRole("button", { name: "Issue purchase order" }).click();
    await workbench
        .getByRole("button", { name: "Post partial receipt" })
        .click();
    await expect(preview.getByRole("status")).toContainText(
        "valuation/outbox evidence recorded",
    );
    await expect(workbench.getByText("Partially received")).toBeVisible();
    await expect(workbench.getByText("PKR 105.454545")).toBeVisible();
    await expect(preview.getByText("12.000000 pc")).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t018-receiving-valuation-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t018-receiving-valuation-dark.png",
            fullPage: true,
        });
    }
    await workbench
        .getByRole("button", { name: "Reverse latest receipt" })
        .click();
    await expect(preview.getByRole("status")).toContainText(
        "Latest receipt reversed",
    );
});

test("supplier return and payment workbench separates physical, credit and allocation evidence in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Products", "Purchasing");
    const preview = page.locator(".purchasing-preview");
    const ledger = preview.locator(".supplier-ledger-workbench");
    await expect(
        ledger.getByRole("heading", { name: "Supplier balance workbench" }),
    ).toBeVisible();
    await expect(
        ledger.getByRole("button", { name: "Post 3-piece return" }),
    ).toBeDisabled();
    await preview.getByRole("button", { name: "Issue purchase order" }).click();
    await preview.getByRole("button", { name: "Post partial receipt" }).click();
    await ledger.getByRole("button", { name: "Post 3-piece return" }).click();
    await expect(ledger.getByText("PKR 375.00 · expected")).toBeVisible();
    await expect(ledger.getByText("Awaiting CN")).toBeVisible();
    await ledger.getByRole("button", { name: "Confirm CN-900" }).click();
    await ledger
        .getByRole("button", { name: "Record supplier payment" })
        .click();
    await expect(ledger.getByText("PKR 375.00 · confirmed")).toBeVisible();
    await expect(ledger.getByText("PKR 625.00 due")).toBeVisible();
    await expect(ledger.getByText("PKR 400 allocated")).toBeVisible();
    await expect(preview.getByRole("status")).toContainText(
        "No bank transfer was initiated",
    );

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t019-supplier-ledger-light.png",
            fullPage: true,
        });
    }
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t019-supplier-ledger-dark.png",
            fullPage: true,
        });
    }
});

test("checkout preserves cart state across scanning, suspension, network loss and theme changes", async ({
    page,
}) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const checkout = page.locator(".checkout-preview");
    await expect(
        checkout.getByRole("heading", { name: "Checkout", exact: true }),
    ).toBeVisible();
    await expect(checkout.getByText("PKR 1290.92")).toBeVisible();

    await checkout.getByRole("button", { name: "Scan tea" }).click();
    await expect(checkout.getByLabel("Karak tea quantity")).toHaveValue("3");
    await checkout.getByRole("button", { name: "Scan 1.250 kg" }).click();
    await expect(checkout.getByLabel("Red apples quantity")).toHaveValue(
        "1.25",
    );
    await checkout.getByRole("button", { name: "Try expired batch" }).click();
    await expect(checkout.getByRole("status")).toContainText("expired");

    await checkout.getByRole("button", { name: "Suspend cart" }).click();
    await expect(
        checkout.getByText("suspended", { exact: true }),
    ).toBeVisible();
    await checkout.getByRole("button", { name: "Resume cart" }).click();
    await checkout
        .getByRole("button", { name: "Simulate network loss" })
        .click();
    await expect(checkout.getByRole("alert")).toContainText(
        "Cart changes are preserved",
    );

    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(checkout.getByLabel("Red apples quantity")).toHaveValue(
        "1.25",
    );
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t021-checkout-dark.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use light mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t021-checkout-light.png",
            fullPage: true,
        });
    }
});

test("sale payment fully settles split tenders and reports each outcome truthfully", async ({
    page,
}) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const checkout = page.locator(".checkout-preview");
    await checkout.getByRole("button", { name: "Continue to payment" }).click();
    await expect(
        checkout.getByRole("heading", { name: "Take payment" }),
    ).toBeVisible();
    await expect(checkout.getByText("Fully settled")).toBeVisible();

    await checkout
        .getByRole("button", { name: "Use split cash + wallet example" })
        .click();
    await expect(
        checkout.getByLabel("Staff-declared external reference"),
    ).toHaveValue("EASYP-STAFF-9421");
    await expect(
        checkout.getByText(/has not verified this payment/),
    ).toBeVisible();
    await checkout.getByRole("button", { name: "Finalize sale" }).click();
    await expect(
        checkout.getByRole("heading", { name: "SALE-01J8K7M42F" }),
    ).toBeVisible();
    await expect(checkout.getByText("Ready · version 1")).toBeVisible();
    await expect(checkout.getByText("Receipt printing")).toBeVisible();
    await expect(checkout.getByText("Fiscal submission")).toBeVisible();
    const saleOutcomes = checkout.getByRole("complementary", {
        name: "Sale outcomes",
    });
    await expect(
        saleOutcomes.getByText("not requested", { exact: true }),
    ).toBeVisible();
    await expect(
        saleOutcomes.getByText("not required", { exact: true }),
    ).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t022-sale-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await expect(page.locator("html")).toHaveAttribute(
            "data-theme",
            "dark",
        );
        await page.screenshot({
            path: "docs/evidence/t022-sale-dark.png",
            fullPage: true,
        });
    }
});

test("receipt printing keeps sale finality separate and recovers from helper failures in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const checkout = page.locator(".checkout-preview");
    await checkout.getByRole("button", { name: "Continue to payment" }).click();
    await checkout.getByRole("button", { name: "Finalize sale" }).click();
    const printing = checkout.getByRole("region", { name: "Receipt & drawer" });
    await expect(
        printing.getByText("sale posted", { exact: true }),
    ).toBeVisible();
    await expect(printing.getByText(/cannot change the sale/)).toBeVisible();

    await printing.getByLabel("Simulate helper").selectOption("paper_out");
    await printing.getByRole("button", { name: "Print original" }).click();
    await expect(printing.getByText("failed", { exact: true })).toBeVisible();
    await expect(printing.getByText(/sale is still posted/)).toBeVisible();
    await expect(
        checkout.getByRole("heading", { name: "SALE-01J8K7M42F" }),
    ).toBeVisible();

    await printing.getByLabel("Simulate helper").selectOption("ready");
    await printing.getByLabel("Reprint reason").fill("Customer requested copy");
    await printing.getByRole("button", { name: "Print marked copy" }).click();
    await expect(
        printing.getByText("printed reported", { exact: true }),
    ).toBeVisible();
    await expect(printing.getByText("2", { exact: true })).toBeVisible();
    await expect(
        printing.getByText("not requested", { exact: true }),
    ).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t024-print-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await expect(page.locator("html")).toHaveAttribute(
            "data-theme",
            "dark",
        );
        await page.screenshot({
            path: "docs/evidence/t024-print-dark.png",
            fullPage: true,
        });
    }
});

test("paired customer display follows the register in a read-only second window with an independent theme", async ({
    page,
}) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const checkout = page.locator(".checkout-preview");
    const controls = checkout.getByRole("region", {
        name: "Customer display controls",
    });
    await expect(controls.getByText(/works normally without/)).toBeVisible();
    await controls.getByRole("button", { name: "Enable display" }).click();
    await expect(
        controls.getByText(/Pairing .* register LAB-01/),
    ).toBeVisible();

    const popupPromise = page.waitForEvent("popup");
    await controls
        .getByRole("button", { name: "Open customer display" })
        .click();
    const display = await popupPromise;
    await display.waitForLoadState();
    const screen = display.getByRole("main", { name: "Customer display" });
    await expect(
        screen.getByRole("heading", { name: "Your order" }),
    ).toBeVisible();
    await expect(screen.getByText("Karak tea", { exact: true })).toBeVisible();
    await expect(screen.getByText("PKR 1290.92")).toBeVisible();
    await expect(screen.getByText("Ayesha Khan")).toHaveCount(0);
    await expect(screen.getByText("Walk-in customer")).toHaveCount(0);
    await expect(
        controls.getByText("connected", { exact: true }),
    ).toBeVisible();

    await display.getByRole("button", { name: "Use dark mode" }).click();
    await expect(display.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await checkout.getByRole("button", { name: "Scan tea" }).click();
    await expect(screen.getByText("PKR 1503.32")).toBeVisible();

    await checkout.getByRole("button", { name: "Suspend cart" }).click();
    await expect(
        screen.getByRole("heading", { name: "This sale is paused." }),
    ).toBeVisible();
    await expect(screen.getByText("Karak tea", { exact: true })).toHaveCount(0);
    await checkout.getByRole("button", { name: "Resume cart" }).click();
    await expect(screen.getByText("Karak tea", { exact: true })).toBeVisible();

    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await display.screenshot({
            path: "docs/evidence/t025-display-dark.png",
            fullPage: true,
        });
        await display.getByRole("button", { name: "Use light mode" }).click();
        await display.screenshot({
            path: "docs/evidence/t025-display-light.png",
            fullPage: true,
        });
    }
    await controls.getByRole("button", { name: "Simulate disconnect" }).click();
    await expect(
        controls.getByText("disconnected", { exact: true }),
    ).toBeVisible();
});

test("offline cash sale survives reload and unresolved evidence is preserved in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const offline = page.getByRole("region", { name: "Durable offline queue" });
    await expect(
        offline.getByText("writer tab", { exact: true }),
    ).toBeVisible();
    await offline
        .getByRole("button", { name: "Queue offline cash sale" })
        .click();
    await expect(
        offline.getByText("Pending").locator("..").getByText("1"),
    ).toBeVisible();
    await expect(offline.locator(".offline-message")).toContainText(
        "committed together",
    );
    await page.reload();
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    const restored = page.getByRole("region", {
        name: "Durable offline queue",
    });
    await expect(restored.getByText("queued", { exact: true })).toBeVisible();
    await restored.getByRole("button", { name: "Simulate disk full" }).click();
    await expect(restored.locator(".offline-message")).toContainText(
        "No receipt or sale was created",
    );
    await restored
        .getByRole("button", { name: "Simulate reconciliation conflict" })
        .click();
    await expect(restored.getByText("conflict", { exact: true })).toBeVisible();
    await expect(
        restored.getByText(/manager review is required/),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t023-offline-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t023-offline-dark.png",
            fullPage: true,
        });
    }
});

test("only one checkout tab owns offline writes", async ({ page, context }) => {
    await page.goto("/");
    await page.getByRole("button", { name: "checkout", exact: true }).click();
    await expect(page.getByText("writer tab", { exact: true })).toBeVisible();
    const second = await context.newPage();
    await second.goto("/");
    await second.getByRole("button", { name: "checkout", exact: true }).click();
    await expect(
        second.getByText("read-only tab", { exact: true }),
    ).toBeVisible();
    await expect(
        second.getByRole("button", { name: "Queue offline cash sale" }),
    ).toBeDisabled();
});

test("merchant-wide loyalty reserves online and explains spent-points return policy", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Customers", "Loyalty");
    const loyalty = page.locator(".loyalty-preview");
    await expect(
        loyalty.getByRole("heading", {
            name: "Points follow the member, not the branch.",
        }),
    ).toBeVisible();
    await expect(
        loyalty.getByText("128.4000", { exact: true }).first(),
    ).toBeVisible();
    await loyalty.getByRole("button", { name: "Reserve reward" }).click();
    await expect(loyalty.getByText("28.4000", { exact: true })).toBeVisible();
    await loyalty
        .getByRole("button", { name: "Preview spent-points return" })
        .click();
    await expect(
        loyalty.getByText("-12.3500", { exact: true }).first(),
    ).toBeVisible();
    await expect(loyalty.getByText("Repayment in progress")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t027-loyalty-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t027-loyalty-dark.png",
            fullPage: true,
        });
    }
});

test("public loyalty uses one limited mobile form and read-only refresh", async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/");
    await page.getByRole("button", { name: "public", exact: true }).click();
    const publicLoyalty = page.locator(".public-loyalty");
    await expect(
        publicLoyalty.getByRole("heading", { name: "Everyday Points" }),
    ).toBeVisible();
    await expect(
        publicLoyalty.getByText(/does not verify identity/),
    ).toBeVisible();
    await publicLoyalty
        .getByRole("button", { name: "Join or check points" })
        .click();
    await expect(publicLoyalty.getByText("A••••• K•••")).toBeVisible();
    await expect(publicLoyalty.getByText("128.4000")).toBeVisible();
    await expect(publicLoyalty.getByText("Ayesha Khan")).toHaveCount(0);
    await publicLoyalty
        .getByRole("button", { name: "Refresh balance" })
        .click();
    await expect(publicLoyalty.getByText(/Read-only lookups: 1/)).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t028-public-loyalty-light-mobile.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t028-public-loyalty-dark-mobile.png",
            fullPage: true,
        });
    }
    await publicLoyalty
        .getByRole("button", { name: "Preview inactive programme" })
        .click();
    await expect(
        publicLoyalty.getByText("Programme unavailable", { exact: true }),
    ).toBeVisible();
});

test("workforce schedule, clock and leave review remain clear in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Team", "Workforce");
    const workforce = page.locator(".workforce");
    await expect(
        workforce.getByRole("heading", {
            name: "Today’s team, without the guesswork.",
        }),
    ).toBeVisible();
    await workforce.getByRole("button", { name: "Clock in" }).click();
    await expect(
        workforce.getByText("Clock-in recorded at 09:02"),
    ).toBeVisible();
    await workforce
        .getByRole("button", { name: "Attendance", exact: true })
        .click();
    await expect(
        workforce.getByRole("columnheader", { name: "Team member" }),
    ).toBeVisible();
    await workforce.getByRole("button", { name: "Leave" }).click();
    await expect(workforce.getByText("18 days available")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t029-workforce-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t029-workforce-dark.png",
            fullPage: true,
        });
    }
});

test("payroll review explains posting and recorded payout in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Team", "Payroll");
    const payroll = page.locator(".payroll-preview");
    await expect(
        payroll.getByRole("heading", {
            name: "Every rupee, explained before posting.",
        }),
    ).toBeVisible();
    await payroll.getByRole("button", { name: "Approve & post" }).click();
    await expect(payroll.getByText("Posted", { exact: true })).toBeVisible();
    await payroll
        .getByRole("button", { name: "Record remaining payout" })
        .click();
    await expect(payroll.getByText("PKR 74,000.00")).toHaveCount(3);
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t030-payroll-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t030-payroll-dark.png",
            fullPage: true,
        });
    }
});

test("operational finance keeps corrections and settlement provenance visible", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Finance");
    const finance = page.locator(".finance-preview");
    await expect(
        finance.getByRole("heading", {
            name: "Cash out once. Reconcile every source.",
        }),
    ).toBeVisible();
    await finance
        .getByRole("button", { name: "Correct selected record" })
        .click();
    await expect(finance.getByText(/original evidence retained/)).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t031-finance-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t031-finance-dark.png",
            fullPage: true,
        });
    }
});

test("standard reports explain totals and preserve personal filters", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Reports");
    const report = page.locator(".report-preview");
    await expect(
        report.getByRole("heading", {
            name: "Every total leads back to its source.",
        }),
    ).toBeVisible();
    await report.getByRole("button", { name: "Save this filter" }).click();
    await expect(report.getByRole("status")).toContainText(
        "saved for your account only",
    );
    await report.getByRole("button", { name: /Gross margin/ }).click();
    await expect(
        report.getByRole("heading", { name: "Gross margin" }),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t032-reporting-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t032-reporting-dark.png",
            fullPage: true,
        });
    }
});

test("imports preserve dry-run evidence and exports show durable status", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Data transfer");
    const transfer = page.locator(".transfer-preview");
    await expect(
        transfer.getByRole("heading", { name: "Preview first. Commit once." }),
    ).toBeVisible();
    await expect(
        transfer.getByText("No operational records changed"),
    ).toBeVisible();
    await transfer
        .getByRole("button", { name: "Use corrected file simulation" })
        .click();
    await expect(
        transfer.getByText("Committed", { exact: true }),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t033-data-transfer-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t033-data-transfer-dark.png",
            fullPage: true,
        });
    }
});

test("notification center separates operational alerts from receipt delivery", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Notifications");
    const center = page.locator(".notification-preview");
    await expect(
        center.getByRole("heading", {
            name: "Operational signals without the noise.",
        }),
    ).toBeVisible();
    await center.getByRole("button", { name: /Batch expires/ }).click();
    await expect(
        center.getByText(/Payroll alerts reveal no pay amounts/),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t034-notifications-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t034-notifications-dark.png",
            fullPage: true,
        });
    }
});

test("integration hub configures merchant services safely in both themes", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Integrations");
    const hub = page.locator(".integration-hub");
    await expect(
        hub.getByRole("heading", { name: "Services belong to each merchant." }),
    ).toBeVisible();
    await expect(hub.getByText("6/10")).toBeVisible();
    await hub.getByRole("button", { name: "Messages" }).click();
    await expect(hub.getByText("3 services")).toBeVisible();
    await hub.getByRole("button", { name: "Configure WhatsApp" }).click();
    const dialog = page.getByRole("dialog", { name: "Configure WhatsApp" });
    await expect(dialog.getByText("Template approval")).toBeVisible();
    await dialog.getByRole("button", { name: "Check configuration" }).click();
    await expect(
        dialog.getByText(
            "Sandbox ready. No external request or message was sent.",
        ),
    ).toBeVisible();
    await dialog
        .getByRole("button", { name: "Close integration settings" })
        .click();
    await page.getByRole("button", { name: "Use dark mode" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(
        hub.getByRole("heading", { name: "Services belong to each merchant." }),
    ).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t038-integrations-dark.png",
            fullPage: true,
        });
    }
});

test("fiscal workspace keeps unknown outcomes and external gates explicit", async ({
    page,
}) => {
    await page.goto("/");
    await openManagementSection(page, "Operations", "Fiscal");
    const fiscal = page.locator(".fiscal-preview");
    await expect(
        fiscal.getByRole("heading", {
            name: "Local sale and authority outcome stay distinct.",
        }),
    ).toBeVisible();
    await expect(
        fiscal.getByText("Production blocked", { exact: true }),
    ).toBeVisible();
    await fiscal
        .getByRole("button", { name: "Record lookup evidence" })
        .click();
    await expect(fiscal.getByText("Accepted · reconciled")).toBeVisible();
    if (process.env.CAPTURE_VISUAL_EVIDENCE === "1") {
        await page.screenshot({
            path: "docs/evidence/t035-fiscal-light.png",
            fullPage: true,
        });
        await page.getByRole("button", { name: "Use dark mode" }).click();
        await page.screenshot({
            path: "docs/evidence/t035-fiscal-dark.png",
            fullPage: true,
        });
    }
});
