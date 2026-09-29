import { computed, ref } from "vue";

export type StaffRole = "owner" | "manager" | "cashier";

const roleNavigation: Record<StaffRole, string[]> = {
    owner: ["Checkout", "Inventory", "Reports", "Payroll", "Staff", "Audit"],
    manager: ["Checkout", "Inventory", "Reports", "Staff", "Audit"],
    cashier: ["Checkout", "My session"],
};

export function useStaffAccess(initialRole: StaffRole = "cashier") {
    const role = ref<StaffRole>(initialRole);
    const cartReference = ref("CART-1042 · 3 items · PKR 2,190.00");
    const announcement = ref("");
    const approvalUsed = ref(false);

    const navigation = computed(() => roleNavigation[role.value]);

    function switchRole(nextRole: StaffRole): void {
        role.value = nextRole;
        announcement.value = `${nextRole[0]?.toUpperCase()}${nextRole.slice(1)} is active. The register and cart were preserved.`;
    }

    function consumeApproval(): boolean {
        if (approvalUsed.value) return false;
        approvalUsed.value = true;
        return true;
    }

    return {
        announcement,
        approvalUsed,
        cartReference,
        consumeApproval,
        navigation,
        role,
        switchRole,
    };
}
