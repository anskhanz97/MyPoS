# Workforce implementation reference

T029 implements employment records, rota assignments, attendance, leave and frozen payroll inputs. It deliberately keeps four identities separate: a user login, an employment record, a till staff/PIN session and a cash-session custodian. Switching a till PIN therefore does not clock a person in or open a cash drawer.

## Domain rules

- `staff_profiles` is the private employment record. Only owners receive `pay_basis` and `base_pay`; managers can coordinate attendance and leave, and cashiers can read only their own workforce data.
- Rota assignments may cross midnight but may not exceed 24 hours, overlap another planned shift for the employee or intersect approved leave.
- Attendance is an event stream plus a current shift projection. Clock, break and clock-out actions require UUID idempotency keys. A duplicate key with changed data is rejected.
- A missed punch is repaired by an explicit correction event containing actor, reason and before/after values. The original clock evidence remains intact.
- Leave requests cannot overlap pending or approved leave. Approval locks and consumes the relevant annual balance; cancelling approved leave reverses that consumption. Decisions and cancellations write security audit events.
- Payroll input snapshots contain the pay basis, base rate, worked/overtime minutes, approved leave and contributing attendance IDs. The unique employee/period snapshot is immutable; later attendance corrections are inputs to a later payroll adjustment rather than a rewrite.
- Attendance and leave writes are online-only in the launch scope. Offline checkout does not grant an offline workforce writer.

## Operational policy

The merchant owns grace minutes, maximum shift length, required-break threshold and the daily overtime threshold. These values describe company operations, not statutory Pakistan payroll rules. Shifts longer than the maximum close into `needs_review`. Overtime is included only when the employee is eligible.

## Interface and accessibility

The management gallery provides schedule, live attendance and leave views, plus clock feedback and populated/empty/error examples. Keyboard-operable tabs and normal focus styles use the common components. All colors come from shared tokens and were visually reviewed in light and dark themes:

- `docs/evidence/t029-workforce-light.png`
- `docs/evidence/t029-workforce-dark.png`

The attendance CSV is store-scoped, owner/manager authorized, private/no-store and protects spreadsheet consumers from formula injection in employee names/numbers.

## Verification

MySQL feature tests cover retry/collision behavior, breaks, overnight shifts, rota overlap, missed-punch correction, leave overlap/approval/cancellation, audit evidence, salary privacy, payroll freeze immutability and cross-store export scope. The browser scenario covers clock feedback and attendance/leave navigation in both themes.
