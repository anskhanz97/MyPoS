<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

const view = ref<"schedule" | "attendance" | "leave">("schedule");
const clockedIn = ref(false);
const notice = ref("");

function clock(): void {
    clockedIn.value = !clockedIn.value;
    notice.value = clockedIn.value
        ? "Clock-in recorded at 09:02"
        : "Clock-out recorded. Shift queued for manager review.";
}

function selectView(next: "schedule" | "attendance" | "leave"): void {
    view.value = next;
}
</script>

<template>
    <section class="workforce" aria-labelledby="workforce-title">
        <header class="workforce-hero">
            <div>
                <p class="kicker">Workforce · Lahore Main</p>
                <h2 id="workforce-title">
                    Today’s team, without the guesswork.
                </h2>
                <p>
                    Schedule, clock actions and leave stay separate from till
                    sign-in and cash sessions.
                </p>
            </div>
            <div class="clock-card" aria-live="polite">
                <span>{{
                    clockedIn ? "On shift · 03h 18m" : "Not clocked in"
                }}</span>
                <strong>{{ clockedIn ? "09:02" : "—" }}</strong>
                <AppButton variant="primary" @click="clock">{{
                    clockedIn ? "Clock out" : "Clock in"
                }}</AppButton>
            </div>
        </header>

        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <nav class="workforce-tabs" aria-label="Workforce views">
            <button
                v-for="item in ['schedule', 'attendance', 'leave'] as const"
                :key="item"
                :aria-pressed="view === item"
                @click="selectView(item)"
            >
                {{ item[0]?.toUpperCase() }}{{ item.slice(1) }}
            </button>
        </nav>

        <div v-if="view === 'schedule'" class="workforce-grid">
            <article class="panel schedule-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Mon 29 Sep</p>
                        <h3>Shift coverage</h3>
                    </div>
                    <StatusBadge tone="success">Fully covered</StatusBadge>
                </div>
                <div class="timeline" aria-label="Scheduled shifts">
                    <div class="hours">
                        <span>08:00</span><span>12:00</span><span>16:00</span
                        ><span>20:00</span>
                    </div>
                    <div class="shift">
                        <span class="avatar">AK</span>
                        <div>
                            <strong>Ayesha Khan</strong
                            ><small>Counter · 09:00–17:00</small>
                        </div>
                        <span class="shift-bar morning"></span>
                    </div>
                    <div class="shift">
                        <span class="avatar blue">UH</span>
                        <div>
                            <strong>Usman Haq</strong
                            ><small>Floor · 12:00–20:00</small>
                        </div>
                        <span class="shift-bar evening"></span>
                    </div>
                    <div class="shift">
                        <span class="avatar gold">SM</span>
                        <div>
                            <strong>Sana Malik</strong
                            ><small>Counter · 14:00–22:00</small>
                        </div>
                        <span class="shift-bar late"></span>
                    </div>
                </div>
            </article>
            <aside class="panel">
                <p class="eyebrow">Needs attention</p>
                <h3>2 attendance items</h3>
                <ul class="attention">
                    <li>
                        <span>Missed clock-out</span
                        ><strong>Usman · Sun</strong>
                    </li>
                    <li>
                        <span>18 min late</span><strong>Sana · Today</strong>
                    </li>
                </ul>
                <AppButton>Review attendance</AppButton>
            </aside>
        </div>

        <div v-else-if="view === 'attendance'" class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Live attendance</p>
                    <h3>Today’s clock record</h3>
                </div>
                <AppButton>Export CSV</AppButton>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Team member</th>
                            <th>In</th>
                            <th>Break</th>
                            <th>Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ayesha Khan</td>
                            <td>09:02</td>
                            <td>30m</td>
                            <td>—</td>
                            <td>
                                <StatusBadge tone="success"
                                    >On shift</StatusBadge
                                >
                            </td>
                        </tr>
                        <tr>
                            <td>Usman Haq</td>
                            <td>12:00</td>
                            <td>—</td>
                            <td>—</td>
                            <td>
                                <StatusBadge tone="info">Scheduled</StatusBadge>
                            </td>
                        </tr>
                        <tr>
                            <td>Sana Malik</td>
                            <td>14:18</td>
                            <td>—</td>
                            <td>—</td>
                            <td>
                                <StatusBadge tone="warning">Late</StatusBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="workforce-grid">
            <article class="panel">
                <p class="eyebrow">My leave</p>
                <h3>18 days available</h3>
                <div class="balance"><span style="width: 62%"></span></div>
                <p>7 used · 3 pending · Annual leave 2026</p>
                <AppButton variant="primary">Request leave</AppButton>
            </article>
            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Approval queue</p>
                        <h3>1 request waiting</h3>
                    </div>
                    <StatusBadge tone="warning">Manager action</StatusBadge>
                </div>
                <div class="request">
                    <span class="avatar">AK</span>
                    <div>
                        <strong>Ayesha Khan</strong
                        ><small>3–5 Oct · Annual leave · 3 days</small>
                    </div>
                </div>
                <div class="actions">
                    <AppButton variant="primary">Approve</AppButton
                    ><AppButton>Decline</AppButton>
                </div>
            </article>
        </div>

        <div class="state-row">
            <StatePanel
                state="empty"
                title="No rota published"
                message="Create the first shift or copy a previous week."
            /><StatePanel
                state="error"
                title="Missing punch"
                message="A manager can correct the record with a reason; the original event remains in the audit trail."
            />
        </div>
    </section>
</template>

<style scoped>
.workforce {
    display: grid;
    gap: 1rem;
}
.workforce-hero {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: linear-gradient(
        135deg,
        var(--surface),
        color-mix(in srgb, var(--accent) 10%, var(--surface))
    );
}
.workforce-hero h2 {
    max-width: 650px;
    margin: 0.25rem 0;
    font-size: clamp(1.8rem, 4vw, 3.25rem);
    line-height: 1;
}
.clock-card {
    min-width: 220px;
    display: grid;
    gap: 0.4rem;
    padding: 1rem;
    border-radius: 0.8rem;
    background: var(--surface);
    box-shadow: var(--shadow);
}
.clock-card strong {
    font-size: 2rem;
}
.notice {
    padding: 0.75rem 1rem;
    border-left: 4px solid var(--success);
    background: var(--surface);
}
.workforce-tabs {
    display: flex;
    gap: 0.5rem;
}
.workforce-tabs button {
    padding: 0.65rem 1rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text);
}
.workforce-tabs button[aria-pressed="true"] {
    background: var(--text);
    color: var(--surface);
}
.workforce-grid,
.state-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1rem;
}
.panel {
    padding: 1.25rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: var(--surface);
}
.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 1rem;
}
.panel h3 {
    margin: 0.2rem 0 1rem;
}
.eyebrow {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.hours {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin-left: 220px;
    color: var(--text-muted);
    font-size: 0.75rem;
}
.shift {
    position: relative;
    display: grid;
    grid-template-columns: 38px 165px 1fr;
    align-items: center;
    gap: 0.6rem;
    min-height: 64px;
    border-top: 1px solid var(--border);
}
.shift small,
.request small {
    display: block;
    color: var(--text-muted);
}
.avatar {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #daf2e5;
    color: #155b37;
    font-weight: 700;
}
.avatar.blue {
    background: #dce9ff;
    color: #244c8b;
}
.avatar.gold {
    background: #f8e8be;
    color: #6a4a05;
}
.shift-bar {
    height: 22px;
    border-radius: 999px;
    background: var(--accent);
}
.morning {
    margin-right: 38%;
}
.evening {
    margin-left: 25%;
    margin-right: 12%;
}
.late {
    margin-left: 38%;
}
.attention {
    list-style: none;
    padding: 0;
}
.attention li {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 0;
    border-bottom: 1px solid var(--border);
}
.attention strong {
    font-size: 0.85rem;
}
.table-wrap {
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    padding: 0.85rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
th {
    color: var(--text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
}
.balance {
    height: 9px;
    border-radius: 20px;
    background: var(--border);
    overflow: hidden;
}
.balance span {
    display: block;
    height: 100%;
    background: var(--accent);
}
.request {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 0;
}
.actions {
    display: flex;
    gap: 0.5rem;
}
@media (max-width: 760px) {
    .workforce-hero {
        display: grid;
    }
    .clock-card {
        min-width: 0;
    }
    .workforce-grid,
    .state-row {
        grid-template-columns: 1fr;
    }
    .hours {
        margin-left: 0;
    }
    .shift {
        grid-template-columns: 38px 1fr;
    }
    .shift-bar {
        grid-column: 1/-1;
        margin: 0 !important;
    }
    .workforce-tabs {
        overflow: auto;
    }
}
</style>
