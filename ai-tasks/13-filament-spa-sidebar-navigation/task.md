# Task: Make Filament Sidebar Navigation Feel Non-Reloading

## Assignee

Other AI implementation task.

## Context

The admin and landlord Filament panels currently navigate with normal full-page requests, so clicking sidebar items can feel like the whole app reloads.

Relevant pages:

- `http://127.0.0.1:8000/admin`
- `http://127.0.0.1:8000/landlord`

Relevant files:

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/LandlordPanelProvider.php`
- `app/Livewire/PropertySwitcher.php`
- `routes/web.php`
- `resources/views/filament/components/language-switcher.blade.php`
- `app/Filament/Resources/InvoiceResource/Concerns/HasInvoiceDocumentActions.php`
- `resources/views/components/invoice-slip-modal.blade.php`

Installed stack supports this:

- Filament v3 supports panel `->spa()`.
- Livewire v3 supports `wire:navigate`.
- Filament automatically adds `wire:navigate` to internal Filament links when SPA mode is enabled.

## Goal

Enable SPA-style navigation for Filament sidebar/resource/page navigation so moving between admin or landlord pages does not do a full browser reload.

Use Filament's built-in SPA mode instead of manually editing sidebar Blade or vendor files.

## Required Implementation

### 1. Enable SPA Mode For Both Panels

Add `->spa()` to:

- `AdminPanelProvider::panel()`
- `LandlordPanelProvider::panel()`

Place it near other panel-level UX options such as:

- `->sidebarCollapsibleOnDesktop()`
- `->databaseNotifications()`
- `->colors(...)`

### 2. Add SPA URL Exceptions

Add `->spaUrlExceptions([...])` so routes that must fully reload or download files are not handled by Livewire navigation.

Required exceptions:

- locale switch routes:
  - `/locale/*`
  - `route('locale.switch', 'en')`
  - `route('locale.switch', 'km')`
- logout routes:
  - `/logout`
  - tenant portal logout if it could appear in shared views
- auth routes:
  - `/login`
  - `/landlord/login`
- PDF / Excel download or stream routes:
  - `/landlord/invoices/*/pdf*`
  - `/landlord/invoices/*/excel*`
  - routes named `invoices.pdf`
  - routes named `invoices.excel`
- external links:
  - `mailto:*`
  - fully external URLs
- cross-panel navigation:
  - admin panel should not SPA-navigate into `/landlord/*`
  - landlord panel should not SPA-navigate into `/admin/*`

If Filament's exception API expects exact URLs, build the list with `url(...)` or route helpers. If wildcard patterns are supported in the installed Filament version, use patterns carefully and verify they work.

### 3. Do Not Break Property Switcher

`app/Livewire/PropertySwitcher.php` currently redirects with `navigate: false`.

Keep it that way unless testing proves SPA navigation is safe. Switching active property changes sidebar context and resource scoping, so full refresh is acceptable there.

### 4. Add Unsaved Change Protection

Consider adding:

- `->unsavedChangesAlerts()`

to both Filament panels if forms can be left dirty and sidebar navigation becomes faster/easier to trigger.

If added, verify create/edit forms warn before leaving unsaved changes.

### 5. Avoid Vendor Edits

Do not edit:

- `vendor/filament/*`
- Filament sidebar vendor views
- Livewire vendor files

The fix should live in panel providers and, only if necessary, local custom view/action configuration.

## Expected Behavior

After implementation:

- Clicking sidebar items in `/admin` changes content without a full white-page reload.
- Clicking sidebar items in `/landlord` changes content without a full white-page reload.
- Browser URL still updates.
- Back/forward browser buttons still work.
- Active sidebar item still updates correctly.
- Language switch still performs a real reload and changes locale.
- PDF/Excel actions still download/stream documents correctly.
- Logout still works correctly.
- Cross-panel links still reload normally.
- Property switcher still refreshes the landlord context correctly.

## Files To Inspect First

Inspect these before editing:

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/LandlordPanelProvider.php`
- `app/Livewire/PropertySwitcher.php`
- `routes/web.php`
- `vendor/filament/filament/src/Panel/Concerns/HasSpaMode.php`

Do not rely on assumptions about wildcard exception support. Confirm behavior in the installed version.

## Testing / Verification

Run syntax checks:

```bash
php -l app/Providers/Filament/AdminPanelProvider.php
php -l app/Providers/Filament/LandlordPanelProvider.php
php artisan route:list --path=admin
php artisan route:list --path=landlord
```

Manual browser checks:

1. Open `/admin`.
2. Click admin sidebar resources/pages.
3. Confirm the URL and content update without a full browser reload.
4. Click language switch in admin; confirm full reload and locale change.
5. Open `/landlord`.
6. Click landlord sidebar resources/pages.
7. Confirm the URL and content update without a full browser reload.
8. Use the property switcher; confirm the active property context changes correctly.
9. Open an invoice action and verify PDF/Excel links still stream/download.
10. Test logout.
11. Test browser back/forward.
12. Open a create/edit form, type unsaved data, click a sidebar item, and confirm the unsaved-change behavior is acceptable.

If route-list or manual checks fail because local MySQL is unavailable, report the exact blocker and still run syntax checks.

## Acceptance Criteria

- Both `/admin` and `/landlord` panels use Filament SPA mode.
- Sidebar navigation feels non-reloading for normal internal Filament pages.
- Locale switching, logout, PDF/Excel downloads, and cross-panel links still behave correctly.
- No vendor files are edited.
- Property switcher remains safe and does not leave stale active-property UI.
- The change is minimal and isolated to panel/provider-level configuration unless a local exception is required.
