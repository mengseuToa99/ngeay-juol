# Task: Landlord Subscription Expiry Billing And Access Lifecycle

## Assignee

Other AI implementation task.

## Context

RentWise has two separate billing concepts:

- Tenant rent/utility invoices use `App\Models\Invoice`.
- Landlord platform plan billing uses `App\Models\Subscription`, `SubscriptionPlan`, and `SubscriptionPayment`.

Do not mix these flows. When a landlord plan ends, do not create a tenant rent invoice. Create or track a platform subscription charge/payment for the landlord plan.

Relevant current code:

- `app/Services/SubscriptionService.php`
- `app/Console/Commands/ProcessSubscriptions.php`
- `app/Http/Middleware/EnsureActiveSubscription.php`
- `app/Models/Subscription.php`
- `app/Models/SubscriptionPayment.php`
- `app/Models/SubscriptionPlan.php`
- `app/Enums/SubscriptionAccess.php`
- `app/Enums/SubscriptionStatus.php`
- `app/Enums/SubscriptionPaymentStatus.php`
- `app/Enums/SubscriptionAction.php`
- `app/Filament/Resources/SubscriptionResource.php`
- `app/Filament/Resources/SubscriptionPaymentResource.php`
- `routes/console.php`
- `docs/SUBSCRIPTIONS.md`

Current lifecycle already includes:

- scheduled `subscriptions:process --renew --sweep --recompute --dunning`
- `SubscriptionService::autoRenewDue()`
- `SubscriptionService::renew()`
- `SubscriptionService::markExpired()`
- `SubscriptionService::effectiveAccess()`
- dunning reminders for expiring soon, past due, and grace ending soon
- `SubscriptionAccess::Full`, `PastDue`, `ReadOnly`, and `Revoked`

## Goal

Finish the landlord subscription expiry flow so an ended plan creates a pending platform subscription charge, keeps the landlord active during grace, then restricts access after grace if payment is not completed.

## Business Policy

Use this policy:

1. Active or trial subscription before `ends_at`
   - landlord has full access.
   - dunning reminders may warn before expiry.

2. On or after `ends_at`
   - attempt auto-renew for supported gateways.
   - if auto-renew succeeds, renew subscription and record successful `SubscriptionPayment`.
   - if auto-renew is not supported or fails, create one pending subscription payment for the next plan period.

3. Grace period
   - landlord remains writable during grace.
   - access should resolve to `SubscriptionAccess::PastDue`.
   - show/notify that payment is required.

4. After `grace_ends_at`
   - landlord should no longer be able to create/edit operational records.
   - preferred MVP behavior: `SubscriptionAccess::ReadOnly` during retention.
   - after retention window, access becomes `SubscriptionAccess::Revoked`.

5. Manual suspension
   - `SubscriptionStatus::Suspended` remains an admin-only/manual action for abuse, fraud, or serious support cases.
   - do not use `Suspended` for normal unpaid expiry.

6. Tenant portal
   - tenants must keep access to their portal and invoices even if the landlord subscription is past due, read-only, or revoked.

## Main Implementation Requirements

### 1. Pending Subscription Payment Generation

Add a service method, for example:

- `SubscriptionService::ensurePendingRenewalPayment(Subscription $subscription): ?SubscriptionPayment`

Behavior:

- Create a `SubscriptionPayment` with `SubscriptionPaymentStatus::Pending` when a subscription is due or past due and no successful renewal exists for the next period.
- Use the subscription snapshot values:
  - `subscription_id`
  - `landlord_id`
  - `plan_id`
  - `amount` from subscription price
  - `currency`
  - `method` should be a safe manual/default method, likely `PaymentMethod::BankTransfer`
  - `status` = `Pending`
  - `paid_at` = `null`
  - `covers_from` = current `ends_at`
  - `covers_to` = `interval->addInterval(covers_from)`
  - `gateway` = `manual`
  - `note` = localized note such as `Subscription renewal pending payment`
- Make this idempotent. Re-running the scheduler must not create duplicate pending payments for the same subscription and coverage period.
- If there is already a pending or succeeded payment for the same `subscription_id`, `covers_from`, and `covers_to`, do not create another one.

### 2. Integrate With Scheduled Processing

Update `ProcessSubscriptions` / `SubscriptionService` so lifecycle processing does this order:

1. auto-renew due subscriptions
2. create pending manual renewal payments for due subscriptions that were skipped or failed
3. send dunning reminders
4. mark expired/past-grace records
5. recompute unit counts

Keep the command options clear. Add a specific option if useful, for example:

- `--bill-renewals`

If adding an option, update `routes/console.php` so the daily scheduled command runs it.

### 3. Manual Payment Completion Flow

When admin records a successful subscription payment for a pending renewal:

- subscription should renew exactly once.
- subscription status becomes `Active`.
- `ends_at` extends to the new coverage end.
- `grace_ends_at` recalculates from the new `ends_at`.
- pending payment should become `Succeeded`, or the system should create a successful payment and close/cancel the pending one. Pick one approach and keep it consistent.

Important: avoid double-renewal if admin edits an already successful payment.

Inspect these files before changing behavior:

- `app/Filament/Resources/SubscriptionPaymentResource/Pages/CreateSubscriptionPayment.php`
- `app/Filament/Resources/SubscriptionPaymentResource/Pages/EditSubscriptionPayment.php`
- `app/Filament/Resources/SubscriptionPaymentResource.php`
- `app/Filament/Resources/SubscriptionResource/RelationManagers/SubscriptionPaymentRelationManager.php`

### 4. Access Enforcement

Current `effectiveAccess()` already returns:

- `PastDue` during grace
- `ReadOnly` during retention
- `Revoked` after retention

Make sure the actual landlord panel respects `ReadOnly`:

- `PastDue` may remain writable.
- `ReadOnly` should block create/edit/delete actions in landlord resources.
- `Revoked` should block login/access as currently implemented.

Do not logout landlords for `ReadOnly`; they should be able to view/export data during retention.

If this requires a shared helper or policy check, centralize it instead of patching every page manually.

### 5. Notifications

Use existing notification/deduplication patterns:

- `SubscriptionPastDueNotification`
- `SubscriptionGraceEndingSoonNotification`
- `NotificationDeduplicator`
- `NotificationRecipients::landlordOperators(...)`

Add notification text or stages only if needed.

Minimum expected notifications:

- pending renewal payment created
- payment past due during grace
- grace ending soon
- access moved to read-only/revoked after grace

Avoid duplicate notifications on repeated scheduler runs.

### 6. UI Requirements

Admin:

- Admin can see pending renewal payments in `/admin/subscription-payments`.
- Pending subscription payments should clearly show landlord, plan, amount, coverage dates, status, and method.
- If there is a pending payment, admin should be able to mark it as succeeded without creating duplicate coverage.

Landlord:

- During grace, show a visible warning on `/landlord` that the subscription is past due.
- During read-only, show a visible warning that write actions are disabled until payment is completed.
- Do not expose admin-only controls to landlord users.

Use `__()` for visible strings and add Khmer translations in `lang/km.json`.

## Explicit Non-Goals

- Do not create tenant `Invoice` records for landlord platform subscriptions.
- Do not block tenant portal access.
- Do not delete landlord data after expiry.
- Do not implement a real online payment gateway unless already configured.
- Do not change plan pricing semantics outside subscription renewal.
- Do not use `Suspended` for normal expiry.

## Tests

Create or update tests, preferably:

- `tests/Feature/LandlordSubscriptionExpiryBillingTest.php`

Minimum assertions:

- Due manual subscription creates exactly one pending `SubscriptionPayment`.
- Running the scheduler/process twice does not duplicate the pending payment.
- Auto-renew success creates a succeeded payment and does not create a pending payment.
- Auto-renew failure creates or keeps a pending renewal payment and does not corrupt subscription dates.
- During grace, `effectiveAccess()` returns `PastDue`.
- After grace but inside retention, `effectiveAccess()` returns `ReadOnly`.
- After retention, `effectiveAccess()` returns `Revoked`.
- Tenant portal routes are not blocked by landlord subscription status.
- Admin marking a pending renewal payment as succeeded renews the subscription once.
- Re-saving a succeeded payment does not renew the subscription again.

## Verification

Run:

```bash
php -l app/Services/SubscriptionService.php
php -l app/Console/Commands/ProcessSubscriptions.php
php -l app/Http/Middleware/EnsureActiveSubscription.php
php artisan list --raw | grep subscriptions:process
php artisan schedule:list
php artisan test --filter=LandlordSubscriptionExpiryBillingTest
```

If PHPUnit is blocked by local runtime issues, report the exact blocker. Known possible blockers in this repo include missing `pdo_sqlite` or unavailable MySQL from the shell.

## Acceptance Criteria

- A landlord plan ending creates one pending platform subscription payment when auto-renew does not complete.
- Grace period does not immediately pause the landlord.
- After grace, landlord becomes read-only during retention, then revoked after retention.
- Manual successful payment renews the subscription and restores full access.
- No tenant rent invoices are created for platform subscription billing.
- Tenant portal remains accessible.
- Dunning and pending-payment notifications are idempotent.
- Visible UI strings are localized, including Khmer translations.
