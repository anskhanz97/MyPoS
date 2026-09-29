# MyPoS frontend design system

Version: 1.0 | Owner: T006 | Status: implementation contract

## Direction: register ledger

MyPoS is a Windows-first counter tool. Its visual language borrows from receipt paper, ruled ledgers and physical till status lamps: crisp edges, dense but readable rows, explicit totals and a narrow operational rail. The rail carries merchant, store, register, connectivity and pending-work context so staff do not need to infer where an action will land.

The memorable element is the context rail, not decorative art. Content stays left aligned. Large numbers use tabular figures. Management screens may be information dense; checkout reserves visual weight for the basket and settlement action; the public shell is quieter and contains no staff controls.

This avoids generic equal-card dashboards, gradients, ornamental motion, restaurant/table imagery and unavailable-module navigation. Until a module exists, navigation shows only the component gallery.

## Palette and tokens

Core light references are receipt `#f4f3ed`, paper `#ffffff`, ink `#17211f`, rule `#d5d9d3`, register teal `#006b5e` and attention amber `#a84f00`. Dark references are counter `#0c1211`, raised counter `#17201e`, chalk `#f1f5f2`, rule `#384641`, signal mint `#56d6b8` and attention amber `#ffb45b`.

Components consume semantic variables only: canvas, surface, elevated surface, text, muted text, border, strong border, focus, accent, accent contrast, disabled, success, warning, danger, info, input, selection, overlay and chart series. A token may change appearance between themes without changing its meaning. Add a semantic token when a meaning repeats; do not add a component-specific color alias.

Typography uses locally available Windows faces and has no CDN dependency. `Aptos`, `Segoe UI` and system sans-serif carry interface copy. `Bahnschrift`, `Aptos Display` and system sans-serif carry totals and compact numeric status. Numeric content uses `font-variant-numeric: tabular-nums`.

## Layouts

```text
Management / checkout desktop
┌──────────── context rail ────────────┬─ current location / status / theme ─┐
│ merchant                             │                                     │
│ store + register                     │ workspace                           │
│ navigation                           │                                     │
│ connectivity + pending work          │                                     │
└──────────────────────────────────────┴─────────────────────────────────────┘

Public / narrow
┌─ product name ─ context ─ theme ─┐
│ single-column workspace          │
│ controls remain in reading order │
└──────────────────────────────────┘
```

Desktop shells use the rail from 1024px upward. Below that, context becomes a compact header and the navigation becomes a horizontally scrollable list with no trapped controls. The minimum supported responsive width is 320px. Checkout qualification starts at 1280×720 in T006; later hardware qualification may add agreed till resolutions.

## Theme preference contract

The typed theme service accepts `light` or `dark`; no implicit system mode is exposed. Staff preference key: `mypos.theme.staff:{staffId}`. Before authentication, `anonymous` is used and every staff choice also updates `mypos.theme.register` as the offline register fallback. Public pages use `mypos.theme.public` and never overwrite staff/register preference.

The Blade bootstrap restores staff preference before CSS and Vue execute. Vue changes the root `data-theme` attribute and storage synchronously. A toggle updates display only: it does not reload, change store context, reset form/cart state or move focus away from the toggle. A future authenticated preference endpoint may mirror the staff value after login; local state remains the offline fallback.

## Component rules

- Minimum interactive target is 44px. Every control has a visible `:focus-visible` ring.
- Primary, secondary, quiet and danger buttons preserve text/icon meaning in both themes. Disabled state is structural and uses `disabled`, not color alone.
- Fields always have a visible label. Help and error text receive stable IDs and `aria-describedby`; invalid fields use `aria-invalid`.
- Money is prefixed with `PKR` and quantities expose their unit. Inputs remain strings so business code never obtains floating-point money.
- Status badges include text or an icon in addition to color. Toasts use live-region semantics. Dialogs restore focus through the native dialog lifecycle.
- Tables retain semantic headers and become horizontally scrollable at narrow widths. Numeric cells align right with tabular figures.
- Loading uses labelled skeletons with reduced-motion support. Empty and error states state the next useful action. Validation examples describe how to fix the value.
- Print media forces black text, white surfaces, visible table rules and hides interactive navigation, toggles and toasts.

## Extension checklist

Use existing tokens and components first. Add stories/states to the gallery with the component. Verify light and dark, keyboard order, focus, 320px overflow, 1280×720 till view and print media. Business permissions remain server-enforced; hiding a control is never authorization.
