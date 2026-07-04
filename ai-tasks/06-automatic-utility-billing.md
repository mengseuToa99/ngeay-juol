# Task 06: Complete Automatic Utility Billing

## Goal

Make scheduled monthly billing generate complete tenant invoices, including rent and eligible utility usage, not rent-only invoices.

## Current State

- `app/Console/Commands/GenerateRentInvoices.php` runs monthly from `routes/console.php`.
- It currently creates rent-only invoices through `InvoiceBuilderService`.
- `app/Filament/Pages/MonthlyBilling.php` has the richer manual flow for due rooms and meter readings.
- `InvoiceBuilderService` already supports `usages`.
- `UtilityBillingService` already resolves utility price and waivers.

## Main Gap

The scheduled command does not attach utility usage records to generated invoices. If the landlord expects automatic monthly bills, utility charges are missing unless they use the manual Monthly Billing page.

## Files To Inspect First

- `app/Console/Commands/GenerateRentInvoices.php`
- `app/Filament/Pages/MonthlyBilling.php`
- `app/Services/InvoiceBuilderService.php`
- `app/Services/UtilityBillingService.php`
- `app/Models/UtilityUsage.php`
- `app/Models/PropertySetting.php`
- `routes/console.php`
- `tests/Feature/*`

## Requirements

1. Add an automatic utility-inclusion path to `invoices:generate-rent`.
2. Only include utility usages for the invoice rental/unit and billing period.
3. Do not double-bill utility usages already attached to an invoice line.
4. Preserve idempotency per rental/period.
5. Respect utility waivers through the existing `InvoiceBuilderService` and `UtilityBillingService`.
6. Keep manual Monthly Billing behavior intact.
7. Make behavior configurable if needed, preferably through existing `PropertySetting`.

## Suggested Approach

- Add a query that finds `UtilityUsage` records for the rental unit where:
  - `reading_date` is inside the billing period, or aligned with the period logic already used by `MonthlyBilling`.
  - no `InvoiceLine` already references the usage.
- Pass those usage IDs into `InvoiceBuilderService::create([... 'usages' => $usageIds])`.
- Consider adding a command option such as `--with-utilities` if automatic inclusion should not always happen.
- If using a setting, add a migration and form field under `PropertySettings`.

## Acceptance Checks

- Running `php artisan invoices:generate-rent --date=2026-07-01` creates invoice lines for rent and unbilled utility usages.
- Running the same command twice does not duplicate invoices or usage lines.
- Waived utilities produce waived invoice lines or zero charges according to current service behavior.
- Existing tests pass.
- Add or update tests covering:
  - rent-only fallback
  - rent + utility usage
  - already billed utility usage is skipped
  - waived utility usage

