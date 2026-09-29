<script setup lang="ts">
import type { Theme } from "../theme/themePreference";
import ThemeToggle from "./ThemeToggle.vue";

export type ShellKind = "management" | "checkout" | "public";

defineProps<{ kind: ShellKind; theme: Theme; nextTheme: Theme }>();
defineEmits<{ toggleTheme: [] }>();
</script>

<template>
    <div class="app-shell" :class="`app-shell--${kind}`">
        <aside v-if="kind !== 'public'" class="context-rail">
            <a class="brand" href="/" aria-label="MyPoS home"
                ><span class="brand__mark">M</span><span>MyPoS</span></a
            >
            <dl class="context-list">
                <div>
                    <dt>Merchant</dt>
                    <dd>Sample Merchant</dd>
                </div>
                <div>
                    <dt>Store</dt>
                    <dd>Lahore Test Store</dd>
                </div>
                <div>
                    <dt>Register</dt>
                    <dd>LAB-01</dd>
                </div>
            </dl>
            <nav aria-label="Primary">
                <a href="#gallery" aria-current="page">UI gallery</a>
            </nav>
            <div class="rail-status">
                <span
                    ><i class="signal signal--online"></i>Online
                    simulation</span
                ><span><b>0</b> pending</span>
            </div>
        </aside>

        <header class="topbar">
            <div>
                <span class="topbar__scope">{{
                    kind === "public"
                        ? "Member preview"
                        : kind === "checkout"
                          ? "Checkout workspace"
                          : "Management workspace"
                }}</span>
                <strong>{{
                    kind === "public"
                        ? "Sample Merchant rewards"
                        : "Lahore Test Store / LAB-01"
                }}</strong>
            </div>
            <ThemeToggle
                :theme="theme"
                :next-theme="nextTheme"
                @toggle="$emit('toggleTheme')"
            />
        </header>

        <main id="gallery" class="workspace" tabindex="-1"><slot /></main>
    </div>
</template>
