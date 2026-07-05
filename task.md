# Task: Add Admin Dashboard Cards Using Landlord Dashboard As Reference

## Context

The app has two Filament panels:

- `/admin` is the platform staff panel for `super_admin` and `support`.
- `/landlord` is the landlord/property-management panel.

The landlord dashboard already has useful dashboard widgets registered in `app/Providers/Filament/LandlordPanelProvider.php`:

- `PortfolioStatsWidget`
- `SubscriptionStatusWidget`
- `RoomStatusWidget`
- `UtilityUsageWidget`
- `RevenueChartWidget`
- `OverdueInvoicesWidget`

The admin dashboard currently only registers:

- `AccountWidget`
- `FilamentInfoWidget`

Goal: add platform-level dashboard cards/widgets to `/admin`, using the landlord dashboard style as the visual and Filament reference, but with admin-specific metrics.

## Important Scope

Do not copy the landlord dashboard directly into `/admin`.

The landlord widgets are property/operator-facing and some use `ActiveProperty`. The admin dashboard should be platform-level and should not depend on the landlord property switcher or an active property.

Keep the change scoped to dashboard widgets and related tests/translations. Do not redesign navigation, auth, subscription workflows, billing logic, or landlord resources.

## Implementation Targets

Expected files to inspect:

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/LandlordPanelProvider.php`
- `app/Filament/Widgets/PortfolioStatsWidget.php`
- `app/Filament/Widgets/SubscriptionStatusWidget.php`
- `app/Models/User.php`
- `app/Models/Property.php`
- `app/Models/Unit.php`
- `app/Models/Subscription.php`
- `app/Models/SubscriptionPayment.php`
- `lang/km.json`
- `tests/Feature/DashboardWidgetsTest.php`

Recommended new file:

- `app/Filament/Widgets/AdminPlatformStatsWidget.php`

Add the new widget to `AdminPanelProvider::widgets()` before `FilamentInfoWidget`.

## Dashboard Cards To Add

Create one `StatsOverviewWidget` for `/admin` with cards similar in layout to `PortfolioStatsWidget`.

Recommended cards:

1. `Landlords`
   - Count users with the `landlord` role.
   - Use Spatie role querying, not a hardcoded profile-only count.

2. `Active subscriptions`
   - Count `Subscription` records with `SubscriptionStatus::Active`.
   - Use `withoutGlobalScopes()` because admin needs platform-wide totals.

3. `Pending subscription payments`
   - Count `SubscriptionPayment` records with `SubscriptionPaymentStatus::Pending`.
   - Use `withoutGlobalScopes()` if the model has landlord scoping.

4. `Monthly subscription revenue`
   - Sum successful subscription payments for the current month.
   - Use `SubscriptionPaymentStatus::Succeeded`.
   - Format as USD like existing dashboard money values.

Optional if useful:

- `Properties`
  - Count all `Property` records platform-wide.

- `Units`
  - Count all `Unit` records platform-wide.

If the widget becomes too crowded, keep the first four cards only.

## Behavior Requirements

- Only visible in the `/admin` panel.
- Visible to platform staff who can access `/admin`.
- Must not require an active property.
- Must not render landlord-only subscription status cards.
- Must not include landlord panel links such as `filament.landlord.*`.
- Use model enums for subscription and payment statuses, not raw magic integers.
- Use `withoutGlobalScopes()` for landlord-scoped models where platform-wide totals are needed.
- Keep visible strings wrapped in `__()`.
- Add Khmer translations for any new strings in `lang/km.json`.

## Visual Reference

Use `PortfolioStatsWidget` as the main reference:

- `StatsOverviewWidget`
- `Stat::make(...)`
- compact card labels
- icons/descriptions/colors where helpful
- money formatting style like `$`.number_format(...)

Do not use `RoomStatusWidget`, `UtilityUsageWidget`, `RevenueChartWidget`, or `OverdueInvoicesWidget` as direct admin widgets unless the requirements change. Those are landlord/property operations widgets.

## Suggested Card Details

Use clear icons and colors:

- `Landlords`
  - Icon: `heroicon-o-users`
  - Color: `info`

- `Active subscriptions`
  - Icon: `heroicon-o-check-circle`
  - Color: `success`

- `Pending subscription payments`
  - Icon: `heroicon-o-clock`
  - Color: `warning` when count is greater than zero, otherwise `success` or `gray`

- `Monthly subscription revenue`
  - Icon: `heroicon-o-banknotes`
  - Color: `success`
  - Description: `current month`

## Tests

Add or update feature tests for the admin widget.

Minimum assertions:

- The admin panel registers the new widget.
- The widget returns platform-wide counts for multiple landlords.
- The widget does not depend on `ActiveProperty`.
- Subscription counts use the correct statuses.
- Monthly revenue only includes successful payments in the current month.
- Pending payment card only counts pending payments.

If `tests/Feature/DashboardWidgetsTest.php` is landlord-focused, either:

- add admin-specific tests in the same file with clear names, or
- create `tests/Feature/AdminDashboardWidgetsTest.php`.

## Verification

Run these checks:

```bash
php -l app/Providers/Filament/AdminPanelProvider.php
php -l app/Filament/Widgets/AdminPlatformStatsWidget.php
php artisan route:list --path=admin
php artisan test --filter=AdminDashboardWidgetsTest
```

If the local test runtime fails because `pdo_sqlite` is missing or MySQL is unavailable, report the exact blocker and still run the `php -l` and route-list checks.

## Acceptance Criteria

- `/admin` dashboard has platform admin cards.
- `/landlord` dashboard remains unchanged.
- Admin cards use platform-wide data, not active-property data.
- No landlord panel resources leak into `/admin`.
- All new visible strings are localized with Khmer entries.
- Tests or fallback verification are documented.
