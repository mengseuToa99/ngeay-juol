# Task 01: Build Maintenance Module UI

## Goal

Expose the existing maintenance request data model through usable landlord/admin and tenant-facing screens.

## Current State

- Models and migrations already exist:
  - `app/Models/MaintenanceRequest.php`
  - `app/Models/MaintenanceMessage.php`
  - `database/migrations/2026_06_21_100014_create_maintenance_requests_table.php`
  - `database/migrations/2026_06_21_100015_create_maintenance_messages_table.php`
- Maintenance requests support tenant, landlord, property, unit, rental, title, description, priority, status, messages, and photo media.
- There is no visible Filament resource and no tenant portal workflow for creating or tracking requests.

## Main Gap

The backend exists, but users cannot practically submit, view, triage, update, or discuss maintenance requests.

## Files To Inspect First

- `app/Models/MaintenanceRequest.php`
- `app/Models/MaintenanceMessage.php`
- `app/Enums/MaintenancePriority.php`
- `app/Enums/MaintenanceStatus.php`
- `app/Policies/MaintenanceRequestPolicy.php`
- `app/Http/Controllers/TenantPortalController.php`
- `resources/views/portal`
- `app/Filament/Resources`
- `app/Providers/Filament/LandlordPanelProvider.php`
- `app/Providers/Filament/AdminPanelProvider.php`

## Requirements

1. Add a Filament `MaintenanceRequestResource` for landlords/managers.
2. Scope landlord users to their own maintenance requests.
3. Allow platform staff to view/manage all requests if existing permission patterns allow it.
4. Add table filters for status, priority, property, and unit.
5. Add actions to update status and priority.
6. Show request messages and allow landlord/manager replies.
7. Support attached photos if practical through existing Spatie Media Library setup.
8. Add tenant portal screens/routes for:
   - creating a maintenance request
   - listing own requests
   - viewing a request
   - posting a reply
9. Keep tenant access strictly limited to their own requests.
10. Localize visible labels with `__()` and add missing Khmer strings.

## Suggested Approach

- Build the Filament resource first for landlord operations.
- Add tenant portal routes under `/portal/maintenance`.
- Extend `TenantPortalController` or create a dedicated `TenantMaintenanceController`.
- Reuse the current logged-in tenant's unit/rental context to auto-fill property/unit/rental IDs.
- Add message creation through a simple form on both landlord and tenant views.
- Use model policies for authorization instead of only controller checks.

## Acceptance Checks

- Landlord can list, view, update, and reply to maintenance requests.
- Tenant can create and view only their own maintenance requests.
- Tenant cannot access another tenant's maintenance request by URL.
- Status and priority labels render correctly.
- Photo upload works or is explicitly deferred in the task result.
- Tests cover tenant create/view authorization and landlord scoped access.

