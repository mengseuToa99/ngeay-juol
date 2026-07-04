# Task 07: Build Notification System

## Goal

Add practical notifications for the core rental workflow: invoice events, overdue invoices, payments, maintenance updates, and subscription reminders.

## Current State

- There is no clear app notification layer yet.
- `ProcessSubscriptions` only logs dunning counts.
- Filament has notification UI available, but no durable notification strategy is implemented.
- Mail configuration exists through Laravel config, but no app-specific notifications are wired.

## Main Gap

Users do not get notified when important events happen. This makes the app hard to operate without manually checking dashboards.

## Files To Inspect First

- `app/Console/Commands/ProcessSubscriptions.php`
- `app/Models/Invoice.php`
- `app/Models/Payment.php`
- `app/Models/MaintenanceRequest.php`
- `app/Models/MaintenanceMessage.php`
- `app/Services/SubscriptionService.php`
- `app/Filament/Widgets/OverdueInvoicesWidget.php`
- `config/mail.php`
- `routes/console.php`

## Notification Events To Cover

1. New invoice generated
2. Invoice overdue
3. Payment recorded
4. Maintenance request created
5. Maintenance request status changed
6. Maintenance message posted
7. Subscription expiring soon
8. Subscription past due

## Requirements

1. Use Laravel Notifications where possible.
2. Support database notifications first.
3. Add mail notifications only where mail config is available and safe.
4. Avoid notifying tenants about landlord platform subscription state.
5. Avoid duplicate scheduled notifications for the same invoice/subscription/day.
6. Keep notifications role-aware:
   - tenants receive tenant-facing invoice/payment/maintenance updates
   - landlords/managers receive operational updates
   - platform staff receive subscription/admin updates only where needed

## Suggested Approach

- Create notification classes under `app/Notifications`.
- Add `notifications` table migration if missing.
- Add notification dispatch points in model events or service methods.
- For scheduled overdue/dunning notifications, use a small idempotency marker.
  - Could be a database notification lookup by type + notifiable + data keys.
  - Or a dedicated log table if stronger tracking is needed.
- Add a Filament notification bell if the panel is not already showing database notifications.

## Acceptance Checks

- Creating an invoice sends a database notification to the tenant.
- Recording a payment sends a database notification to the tenant.
- Creating a maintenance request notifies the landlord/manager.
- Posting a maintenance message notifies the other participant.
- Running subscription processing sends expiring/past-due notifications without duplicates.
- Tests cover at least invoice, payment, maintenance, and subscription reminder notifications.

