# Frontend execution standard — light and dark

Status: user requirement plus proposed implementation defaults. Applies to every UI ticket, including customer and platform surfaces.

## Confirmed requirement

The user explicitly requires two themes: bright/light mode and dark mode, easily toggled on/off. Neither theme is a later cosmetic task. Every feature must be reviewed in both before its ticket is complete.

The frontend-design skill may guide visual design. Apply its token, typography, layout and critique practices to a fast operational POS. Do not import landing-page hero sections, marketing animations or unnecessary decoration into cashier workflows.

## Theme mechanics

- Use semantic CSS variables/tokens for canvas, surface, elevated surface, text, muted text, border, focus, accent, disabled, success, warning, error, input and chart series.
- Components consume semantic tokens, not their own hard-coded light/dark colors. Charts, menus, date pickers, dialogs, skeletons, notifications and empty states participate.
- A visible, labelled toggle is available in the management shell, checkout shell and public member page. Both states are reachable by keyboard; expose accessible checked/pressed state.
- Default to light for a new session unless a persisted explicit choice exists. Provide explicit light/dark selection; an automatic system-following third mode is not required.
- Persist preference locally for offline use and per authenticated user when available. Restore before first paint to avoid a white flash. Do not store credentials with theme state.
- Switching theme must not reload, reset forms, lose a cart, interrupt a scanner, change totals, move focus unexpectedly or alter another user's business permissions.
- On shared tills, the current staff user's preference wins after switch; retain an offline register fallback. Public portal preference is independent from merchant business configuration.
- Print/PDF styles use readable black-on-white output regardless of screen theme. Never send a dark page background to a thermal printer by accident.

## Operational layout

Use a stable navigation shell, clear merchant/store/register context and visible online/offline/pending-sale status. Avoid identical dashboard cards for every datum. Dense tables may be appropriate in management; checkout must favour rapid scan/search, a legible basket, totals and settlement actions.

Create a component gallery before feature screens. Include fields, money/quantity inputs, buttons, tabs, tables, paginated lists, filters, badges, approval dialogs, destructive confirmations, drawers and status notices. Define typography with readable numeric alignment; choose an available licensed font with a reliable fallback and no offline dependency on a CDN.

Keyboard navigation and barcode input are first-class. Do not steal focus while a user edits a quantity or note. Scanner suffix handling and duplicate-event protection must not prevent intentional repeated scans. Keep the main settlement action visually distinct without relying on color alone.

Management pages are responsive; checkout is qualified on agreed Windows till resolutions. Do not imply every peripheral works on phones or iPads. Public member view works on narrow phones without exposing additional customer data.

T021 checkout uses a four-part counter layout: persistent merchant/store/register context, a scanner/customer/staff command bar, searchable category/product shortcuts and a basket with quantity controls plus quote explanation. The `/` shortcut focuses the scanner only outside active text/select controls. Suspended and retrying states stay visible without clearing lines; expired stock produces an explicit block. Theme changes preserve the populated basket. The payment action is visibly disabled until T022 supplies atomic sale posting.

## Accessibility and display rules

Target WCAG 2.2 AA design/checks: contrast, focus visibility, labels, errors, keyboard operation, touch targets and reduced motion. Validate colors rather than assuming that a dark palette is accessible. Test disabled controls and warning/error text in both themes. Do not communicate expired batches, offline state or validation only with red/green color.

English text, sentence-case actions and consistent naming. Preserve Unicode product/customer names and wrapping. Keep implementation details such as queue names, internal IDs and raw payloads out of normal product flows. Diagnostics belong in restricted support views.

## Required visual verification per UI ticket

1. Review light and dark screenshots at the primary till size and a narrow management/member viewport where applicable.
2. Exercise keyboard focus, modal opening/closing, loading, empty, populated, validation and error states.
3. Toggle while a non-empty form/cart is active; verify retained data and focus.
4. Reload, switch staff and start the previously provisioned offline client; verify preference behaviour.
5. Review print/PDF output separately from screen styling.
6. Record actual screenshots/results in the ticket completion notes. Do not claim visual verification solely because CSS compiled.

## Customer display

Second-screen support is optional per register. Show basket, totals and appropriate completion state; hide staff controls, customer contact details and payroll data. Pair read-only with the correct register and handle disconnect/reconnect. Keep theme configurable for that display without changing the cashier screen unexpectedly. A second browser window is an acceptable first Windows implementation if tested; do not require a proprietary monitor protocol.
