import { describe, expect, it } from "vitest";
import { useStaffAccess } from "../../resources/js/auth/staffAccess";

describe("staff access preview", () => {
    it("switches fixed permissions without clearing the cart", () => {
        const access = useStaffAccess("cashier");
        const cart = access.cartReference.value;
        expect(access.navigation.value).not.toContain("Payroll");

        access.switchRole("manager");
        expect(access.cartReference.value).toBe(cart);
        expect(access.navigation.value).not.toContain("Payroll");

        access.switchRole("owner");
        expect(access.navigation.value).toContain("Payroll");
        expect(access.cartReference.value).toBe(cart);
    });

    it("consumes an approval once", () => {
        const access = useStaffAccess();
        expect(access.consumeApproval()).toBe(true);
        expect(access.consumeApproval()).toBe(false);
    });
});
