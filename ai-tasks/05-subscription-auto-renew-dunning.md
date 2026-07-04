# Task 05: Finish Subscription Auto-Renew And Dunning

## Goal

Turn platform subscription lifecycle processing from mostly manual/log-only behavior into a real renewal and reminder flow.

## Current State

- `docs/SUBSCRIPTIONS.md` describes auto-renew, dunning reminders, gateway abstraction, and multiple scheduled jobs.
- Code currently has:
  - `app/Services/SubscriptionService.php`
  - `app/Console/Commands/ProcessSubscriptions.php`
  - `routes/console.php`
  - subscription resources and payment resources
- `ProcessSubscriptions` currently logs expiring/past-due counts.
- No real gateway abstraction or reminder notifications are wired.

## Main Gap

Subscriptions can be assigned and manually renewed, but production-grade auto-renew and dunning behavior is missing.

## Files To Inspect First

- `docs/SUBSCRIPTIONS.md`
- `app/Services/SubscriptionService.php`
- `app/Console/Commands/ProcessSubscriptions.php`
- `app/Models/Subscription.php`
- `app/Models/SubscriptionPayment.php`
- `app/Models/SubscriptionPlan.php`
- `app/Enums/SubscriptionStatus.php`
- `app/Enums/SubscriptionPaymentStatus.php`
- `app/Enums/SubscriptionAccess.php`
- `app/Enums/SubscriptionAction.php`
- `app/Filament/Resources/SubscriptionResource.php`
- `app/Filament/Resources/SubscriptionPaymentResource.php`
- `routes/console.php`

## Requirements

1. Implement real dunning reminders for subscriptions:
   - expiring soon
   - expired / past due
   - grace ending soon
2. Avoid duplicate reminders for the same subscription and reminder stage.
3. Add database notifications at minimum.
4. Add mail notifications only if mail setup is safe and configured.
5. Implement or clearly scaffold a payment gateway abstraction:
   - `PaymentGateway` interface
   - `ManualGateway`
   - placeholder future gateway, only if useful
6. Implement auto-renew for `auto_renew=true` subscriptions where a supported gateway/payment method exists.
7. Keep manual payment recording intact.
8. Ensure failed auto-renew does not corrupt subscription state.
9. Update scheduler/command behavior to match what is actually implemented.
10. Update docs if implementation differs from `docs/SUBSCRIPTIONS.md`.

## Suggested Approach

- Start with dunning notifications because they are lower risk than automatic charging.
- Add notification classes under `app/Notifications`.
- Add an idempotency mechanism for reminder stages.
  - Options: inspect existing database notifications, or add a small `subscription_notification_logs` table.
- Add gateway abstraction with manual/no-op behavior first.
- Add auto-renew logic only for subscriptions with enough payment metadata to safely renew.
- Keep all subscription state changes inside `SubscriptionService`.

## Acceptance Checks

- Running `php artisan subscriptions:process --dunning` creates reminder notifications.
- Running it twice does not create duplicates for the same reminder stage.
- Running `php artisan subscriptions:process --sweep` still marks expired subscriptions correctly.
- Auto-renew either renews eligible subscriptions safely or skips them with a clear reason.
- Manual subscription payment flow still works.
- Tests cover dunning idempotency, failed renewal behavior, and successful manual renewal.

