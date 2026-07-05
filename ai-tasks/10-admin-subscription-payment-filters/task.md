# Task: Add Admin Subscription Payment Filters

## Assignee

Other AI implementation task.

## Context

The admin subscription payments page is:

- `http://127.0.0.1:8000/admin/subscription-payments`

The reference page is the landlord invoices page:

- `http://127.0.0.1:8000/landlord/invoices`

The admin subscription payment table currently has only a status filter in:

- `app/Filament/Resources/SubscriptionPaymentResource.php`

The landlord invoice table has a richer filter pattern in:

- `app/Filament/Resources/InvoiceResource.php`

Use the invoice table filters as the reference, especially the `due_date` period filter with:

- `This month`
- `Last month`
- `Last 2 months`
- `Last 3 months`
- `Last 6 months`
- `This year`
- `Custom`
- `From`
- `Until`
- `All time`

## Goal

Add useful filters to `/admin/subscription-payments` so platform staff can quickly find subscription payments by date, landlord, status, method, and payment coverage period.

## Required Filters

Add these filters to `SubscriptionPaymentResource::table()`:

1. Payment date period filter
   - Follow the same UI/behavior pattern as `InvoiceResource` date filtering.
   - Use `paid_at` as the main date field.
   - Support the same preset periods as landlord invoices:
     - `this_month`
     - `last_month`
     - `last_2_months`
     - `last_3_months`
     - `last_6_months`
     - `this_year`
     - `custom`
   - For `custom`, show `from` and `until` date pickers.
   - If no period is selected, show all records.
   - Add clear filter indicators like the invoice resource does.

2. Status filter
   - Keep the existing `SelectFilter::make('status')`.
   - Continue using `SubscriptionPaymentStatus::class`.

3. Landlord filter
   - Add a searchable/preloaded landlord filter.
   - Filter by `landlord_id` if available on `subscription_payments`.
   - If needed, filter through `subscription.landlord_id`, but prefer the direct `landlord_id` column because the model already has it.
   - Label it `Landlord`.

4. Payment method filter
   - Add a method filter using `PaymentMethod::class`.
   - Label it `Method`.

5. Coverage period filter
   - Add a filter for the subscription coverage dates.
   - Use `covers_from` and `covers_to`.
   - Provide `coverage_from` and `coverage_until` date pickers.
   - Query logic should include payments whose coverage range overlaps the selected range:
     - if only `coverage_from` is set, include records with `covers_to >= coverage_from`
     - if only `coverage_until` is set, include records with `covers_from <= coverage_until`
     - if both are set, include records where the record coverage overlaps that range

## Behavior Requirements

- Keep this scoped to the admin subscription payments resource.
- Do not modify landlord invoice behavior.
- Do not add landlord panel routes or links to the admin resource.
- Use `__()` for every visible filter label, option, placeholder, and indicator string.
- Add missing Khmer translations in `lang/km.json`.
- Use enums instead of raw integers for status and payment method options.
- Keep the current default sort by `created_at desc`.
- Filters must work with platform-wide data. Do not use `ActiveProperty`.
- Do not change access rules. `SubscriptionPaymentResource::canAccess()` should remain super-admin only unless a separate task asks otherwise.

## Reference Implementation Notes

Use `InvoiceResource` as the model for:

- `Tables\Filters\Filter::make(...)`
- filter `form([...])`
- `Forms\Components\Select::make('period')`
- conditional custom date fields with `->visible(...)`
- `->query(function ($query, array $data) { ... })`
- `->indicateUsing(...)`
- `Tables\Filters\Indicator::make(...)`

Adapt field names:

- invoice `due_date` becomes subscription payment `paid_at`
- invoice `payment_status` becomes subscription payment `status`
- invoice custom date fields `from` / `until` can be reused for the `paid_at` filter

## Suggested Filter Order

Use this order:

1. Payment date period
2. Status
3. Landlord
4. Method
5. Coverage period

This keeps the most common filters first and matches the invoice page pattern.

## Tests

Add or update feature tests for the admin subscription payment filters.

Recommended test file:

- `tests/Feature/AdminSubscriptionPaymentFiltersTest.php`

Minimum assertions:

- Filtering by payment status shows only matching `SubscriptionPaymentStatus` records.
- Filtering by payment method shows only matching `PaymentMethod` records.
- Filtering by landlord shows only records for that landlord.
- Payment date period filter:
  - `this_month` includes current-month `paid_at` records.
  - `last_month` includes last-month `paid_at` records.
  - custom `from` / `until` includes only records inside the selected range.
- Coverage period filter includes records whose `covers_from` / `covers_to` overlaps the selected coverage range.
- The admin resource still registers at `/admin/subscription-payments`.

## Verification

Run these checks:

```bash
php -l app/Filament/Resources/SubscriptionPaymentResource.php
php artisan route:list --path=admin/subscription-payments
php artisan test --filter=AdminSubscriptionPaymentFiltersTest
```

If PHPUnit is blocked by the local environment, report the exact blocker. Known possible blockers in this repo include missing `pdo_sqlite` or unavailable MySQL from the shell.

## Acceptance Criteria

- `/admin/subscription-payments` has filters comparable to `/landlord/invoices`.
- The payment date filter supports presets and custom date range.
- Status, landlord, method, and coverage-period filters work together.
- Filter labels and indicators are localized.
- Khmer translations are added for new visible strings.
- `/landlord/invoices` remains unchanged.
