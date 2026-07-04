# Task 08: Complete Khmer Localization Cleanup

## Goal

Find and translate remaining hard-coded English UI strings, especially in Filament resources, pages, relation managers, actions, placeholders, badges, and tenant portal views.

## Current State

- Khmer JSON locale exists at `lang/km.json`.
- Several pages are already partially translated.
- Recent fixes covered invoice PDF Khmer rendering and Utility Waivers labels.
- Many resources still contain hard-coded labels like `->label('Tenant')`, placeholders, headings, action labels, and navigation labels.

## Main Gap

The UI still mixes English and Khmer when the locale is `km`.

## Files To Inspect First

- `lang/km.json`
- `app/Filament`
- `resources/views`
- `app/Enums`
- `app/Http/Middleware/SetLocale.php`
- `resources/views/filament/components/language-switcher.blade.php`

## Requirements

1. Wrap user-facing strings in `__()`.
2. Add missing keys to `lang/km.json`.
3. Do not translate code-only identifiers, route names, class names, or internal comments.
4. Preserve placeholders like invoice numbers and dynamic values.
5. Avoid duplicate JSON keys where possible.
6. Keep ASCII placeholders in PDF where needed for font compatibility, unless Chrome rendering makes it safe.

## Suggested Search Commands

Run variants of:

```bash
rg -n "->label\\('[^']+'\\)|->placeholder\\('[^']+'\\)|->helperText\\('[^']+'\\)|->heading\\('[^']+'\\)|->description\\('[^']+'\\)|Action::make\\('[^']+'\\)" app resources
rg -n "__\\('[^']+'\\)" app resources
```

Also inspect enum `getLabel()` methods because enum labels commonly leak English.

## Priority Areas

1. Landlord panel resources
2. Tenant portal views
3. Admin panel subscription resources
4. Dashboard widgets
5. Auth/login pages
6. Reports and exports

## Acceptance Checks

- `lang/km.json` is valid JSON.
- No obvious hard-coded English labels remain in core Filament resources.
- `/landlord/utility-waivers`, invoices, payments, rentals, units, utilities, and monthly billing show Khmer labels when Khmer locale is active.
- Tenant portal dashboard and invoice page show Khmer labels when Khmer locale is active.
- Existing tests pass.

