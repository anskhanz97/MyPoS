<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
import { type StaffRole, useStaffAccess } from "../auth/staffAccess";

const access = useStaffAccess();
const email = ref("owner@example.test");
const password = ref("");
const loginError = ref("");
const reason = ref("");
const approvalError = ref("");
const loginState = ref<"signed_out" | "signed_in">("signed_out");

function signIn(): void {
    if (password.value.length < 12) {
        loginError.value =
            "Enter the account password (at least 12 characters).";
        return;
    }
    loginError.value = "";
    loginState.value = "signed_in";
}

function switchStaff(role: StaffRole): void {
    access.switchRole(role);
}

function approve(): void {
    if (reason.value.trim().length < 3) {
        approvalError.value = "Enter a reason for this one-time approval.";
        return;
    }
    approvalError.value = "";
    access.consumeApproval();
}
</script>

<template>
    <div class="access-preview">
        <header class="page-heading access-heading">
            <div>
                <p class="kicker">Access control · working preview</p>
                <h1>Identity changes. The register stays put.</h1>
                <p>
                    Account sessions, device-bound PIN switching and narrow
                    approvals use separate credentials and lifetimes.
                </p>
            </div>
            <StatusBadge
                :tone="loginState === 'signed_in' ? 'success' : 'warning'"
                >{{
                    loginState === "signed_in"
                        ? "Account session active"
                        : "Sign-in preview"
                }}</StatusBadge
            >
        </header>

        <section class="gallery-section" aria-labelledby="login-title">
            <div class="section-heading">
                <div>
                    <h2 id="login-title">Secure account session</h2>
                    <p>
                        Internet login uses the account password. Till PINs are
                        never accepted here.
                    </p>
                </div>
            </div>
            <div class="access-form">
                <label class="field"
                    ><span>Email</span
                    ><input
                        v-model="email"
                        autocomplete="username"
                        type="email"
                /></label>
                <label class="field"
                    ><span>Password</span
                    ><input
                        v-model="password"
                        autocomplete="current-password"
                        type="password"
                /></label>
                <AppButton variant="primary" @click="signIn"
                    >Sign in securely</AppButton
                >
                <AppButton variant="quiet">Send recovery link</AppButton>
            </div>
            <p v-if="loginError" class="form-error" role="alert">
                {{ loginError }}
            </p>
        </section>

        <section class="gallery-section" aria-labelledby="switch-title">
            <div class="section-heading">
                <div>
                    <h2 id="switch-title">Registered till staff</h2>
                    <p>
                        PIN proof changes the active staff session only on this
                        authorized device.
                    </p>
                </div>
                <StatusBadge tone="info">{{ access.role.value }}</StatusBadge>
            </div>
            <div class="cart-retained">
                <span>Active cart</span
                ><strong>{{ access.cartReference.value }}</strong>
            </div>
            <div class="role-switcher" aria-label="Switch staff role">
                <button
                    v-for="staffRole in [
                        'cashier',
                        'manager',
                        'owner',
                    ] as StaffRole[]"
                    :key="staffRole"
                    type="button"
                    :aria-pressed="access.role.value === staffRole"
                    @click="switchStaff(staffRole)"
                >
                    <strong>{{ staffRole }}</strong
                    ><small>{{
                        staffRole === "cashier"
                            ? "Checkout only"
                            : staffRole === "manager"
                              ? "Store operations"
                              : "Merchant administration"
                    }}</small>
                </button>
            </div>
            <p class="context-announcement" aria-live="polite">
                {{ access.announcement.value }}
            </p>
            <nav class="permission-nav" aria-label="Authorized navigation">
                <span v-for="item in access.navigation.value" :key="item">{{
                    item
                }}</span>
            </nav>
        </section>

        <section class="gallery-section" aria-labelledby="approval-title">
            <div class="section-heading">
                <div>
                    <h2 id="approval-title">One-time manager approval</h2>
                    <p>
                        Bound to this store, action and target; expires in five
                        minutes and cannot be replayed.
                    </p>
                </div>
                <StatusBadge
                    :tone="access.approvalUsed.value ? 'success' : 'warning'"
                    >{{
                        access.approvalUsed.value
                            ? "Consumed"
                            : "Awaiting approval"
                    }}</StatusBadge
                >
            </div>
            <div class="approval-card">
                <dl>
                    <div>
                        <dt>Action</dt>
                        <dd>No-sale drawer open</dd>
                    </div>
                    <div>
                        <dt>Target</dt>
                        <dd>Register session RS-104</dd>
                    </div>
                    <div>
                        <dt>Approver</dt>
                        <dd>Manager on this store</dd>
                    </div>
                </dl>
                <label class="field"
                    ><span>Reason</span
                    ><input
                        v-model="reason"
                        :disabled="access.approvalUsed.value"
                /></label>
                <p v-if="approvalError" class="form-error" role="alert">
                    {{ approvalError }}
                </p>
                <AppButton
                    variant="primary"
                    :disabled="access.approvalUsed.value"
                    @click="approve"
                    >{{
                        access.approvalUsed.value
                            ? "Approval already used"
                            : "Approve this action once"
                    }}</AppButton
                >
            </div>
        </section>
    </div>
</template>
