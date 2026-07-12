# Dynamic Move-In Billing Rules

## Purpose

This document is an implementation specification for improving Rentwise's tenant move-in billing flow. It is intended to be handed to another AI or developer.

Do not implement every imaginable rental policy as a separate hardcoded setting. Build a small dynamic rule system that supports common presets and property-specific requirements.

The central workflow must be:

> Calculate requirements -> request and allocate payment -> confirm readiness -> activate tenancy.

Creating a rental record must not automatically mean that every move-in requirement has been satisfied.

## Product decision

Rentwise must distinguish these concepts:

1. **First-period rent** pays for the tenant's first rental period.
2. **Last-month rent prepayment** is a rent credit held until the actual final rental period.
3. **Security deposit** is refundable money held for an end-of-tenancy settlement.
4. **Other deposits** may be refundable and tied to keys, utilities, furniture, pets, or another stated purpose.
5. **Fees** are non-refundable charges and are neither deposits nor rent credits.

These amounts may appear together on one move-in payment request, but they must remain separate balances internally.

## Primary use case

A landlord requires one month of rent when the tenant moves in and one month of rent in advance for the tenant's eventual final month.

Example with monthly rent of $300:

| Requirement | Amount | Allocation |
|---|---:|---|
| First-period rent | $300 | First rental period |
| Last-month prepayment | $300 | Actual final rental period |
| Required before move-in | $600 | — |

After payment, Rentwise must show:

- First-period rent applied: $300
- Last-month rent credit held: $300
- Blocking amount outstanding: $0
- Move-in readiness: Ready

The last-month credit must not be assigned permanently to an estimated end date. If the tenant extends the tenancy, the credit moves forward and remains available for the actual final rental period.

## Current implementation

Review these files before making changes:

- `app/Filament/Pages/PropertySettings.php`
- `app/Models/PropertySetting.php`
- `app/Models/Rental.php`
- `app/Services/ProratingService.php`
- `app/Services/InvoiceBuilderService.php`
- `app/Models/Invoice.php`
- `app/Models/InvoiceLine.php`
- `app/Models/Payment.php`
- `app/Enums/FirstMonthBillingMode.php`
- `app/Enums/RentalStatus.php`
- `app/Filament/Resources/RentalResource.php`
- `app/Filament/Resources/UnitResource/RelationManagers/RentalsRelationManager.php`
- `app/Livewire/SimpleAddTenant.php`

Current capabilities include:

- Full-month, daily-prorated, and half-month first-rent calculation.
- A `require_first_month_upfront` property setting.
- A `create_invoice_on_move_in` property setting.
- A property deposit multiplier of zero, one, or two months.
- A per-rental `security_deposit` amount and currency.
- Automatic first-invoice generation when an active rental is saved.

Current problems that must be addressed:

1. `require_first_month_upfront` describes a payment gate, but the active tenancy and room occupancy flow does not enforce that gate.
2. The property deposit multiplier and the per-rental security-deposit amount can disagree.
3. `InvoiceBuilderService` calculates the deposit from the property multiplier rather than treating the agreed rental amount as an intentional snapshot/override.
4. A security deposit is added as a normal ad-hoc invoice line without a complete held-balance, deduction, transfer, and refund lifecycle.
5. Last-month rent prepayment is not modeled separately from a security deposit.
6. Activating a rental, occupying a room, creating an invoice, and satisfying move-in conditions are too tightly coupled in the `Rental` model event.

## Required product scope

### Phase 1: Core requirements

Implement these capabilities:

- Dynamic move-in requirement rules per property.
- Presets for common configurations.
- Fixed or rent-based calculations.
- Per-rental snapshots of the agreed rules and calculated amounts.
- Optional per-rental overrides with a recorded reason.
- A move-in payment request containing separately classified lines.
- Correct allocation of payments to rent, rent credit, deposit, and fee balances.
- Partial payments.
- A configurable move-in payment gate.
- A manager override with actor, time, reason, and remaining balance.
- A readiness check before tenancy activation.
- A last-month credit lifecycle.
- A security-deposit settlement and refund lifecycle.

### Deferred capabilities

The data model may accommodate these later, but do not make the first implementation unnecessarily large:

- Complex promotional pricing.
- Tenant approval/dispute workflow for deposit deductions.
- Jurisdiction-specific legal decisions.
- Automatic forfeiture decisions.
- Complicated accounting exports.
- Unlimited formula builders or user-authored expressions.

## Dynamic rule model

Use structured rule records rather than adding a new boolean or column for every policy.

The exact table and class names may follow project conventions, but a rule needs the following semantics:

| Field | Purpose |
|---|---|
| `property_id` | Property owning the default rule |
| `name` | Landlord-facing label |
| `charge_type` | Classification of the requirement |
| `calculation_type` | How its amount is calculated |
| `calculation_value` | Fixed amount, percentage, or rent multiplier |
| `currency` | Required for fixed monetary amounts |
| `due_timing` | Before move-in, on move-in, or scheduled after move-in |
| `blocks_move_in` | Whether an unpaid required amount blocks readiness |
| `minimum_required` | Full amount, fixed minimum, or percentage required before move-in |
| `refundable` | Whether an unused balance can be refunded |
| `application_policy` | First period, actual final period, or move-out settlement |
| `allow_rental_override` | Whether a tenancy may change the default |
| `sort_order` | Display and calculation order |
| `is_active` | Whether the rule applies to new tenancies |

Use enums or validated constants for rule types. Do not store arbitrary formulas.

### Charge types

At minimum support:

- `first_period_rent`
- `last_month_rent_credit`
- `security_deposit`
- `other_refundable_deposit`
- `non_refundable_fee`

An optional custom landlord-facing name may be used with the appropriate accounting classification.

### Calculation types

At minimum support:

- `fixed_amount`
- `rent_multiplier`
- `percentage_of_rent`
- `first_period_calculation`
- `manual_per_rental`

`first_period_calculation` must reuse the existing full-month, prorated, and half-month logic instead of duplicating it.

### Snapshot requirement

When a tenancy is prepared, copy the applicable property rules into tenancy-specific move-in requirement records. Store:

- Original property rule reference.
- Human-readable name.
- Classification.
- Calculation inputs.
- Calculated amount and currency.
- Amount required before move-in.
- Amount paid/allocated.
- Status.
- Override reason and actor, when applicable.

Later property-setting changes must not silently rewrite an existing tenant's agreement or balances.

## Presets

Property Settings should offer presets, followed by a custom rule editor.

### Flexible

- No mandatory deposit.
- First rent follows its normal due date.
- Payment does not block move-in.

### First and last month

- Calculated first-period rent.
- One month of rent held as a last-month rent credit.
- Both required before move-in.

### First month and deposit

- Calculated first-period rent.
- One month of rent as a refundable security deposit.
- Both required before move-in.

### First, last, and deposit

- Calculated first-period rent.
- One month as last-month rent credit.
- One month as refundable security deposit.
- All required before move-in.

### Custom

- Landlord adds, removes, reorders, and configures supported rule rows.

Selecting a preset should populate editable structured rules. The preset name alone must not be the source of business logic.

## Required tenancy state flow

Do not activate and occupy the unit merely because a tenancy record was created.

Recommended state semantics:

1. `draft`: tenancy is being prepared.
2. `awaiting_payment`: one or more blocking requirements remain unpaid.
3. `ready_for_move_in`: every blocking condition is satisfied or validly overridden.
4. `active`: landlord confirmed the tenant has moved in; the unit becomes occupied.
5. Existing ending/ended states continue to handle departure.
6. A settlement state may be added if needed to distinguish ended occupancy from financially closed tenancy.

If changing the existing `RentalStatus` enum is risky, implement an explicit move-in/readiness status alongside it. Do not overload invoice payment status to represent tenancy readiness.

## Move-in flow

### 1. Prepare tenancy

The landlord chooses the tenant, property/unit, start date, monthly rent, rent currency, and lease details. Rentwise snapshots the applicable move-in rules.

### 2. Preview calculation

Show every requirement before saving or issuing a request.

Example:

| Item | Amount | Required before move-in |
|---|---:|---:|
| Prorated first rent | $150 | $150 |
| Last-month rent credit | $300 | $300 |
| Security deposit | $300 | $300 |
| Total | $750 | $750 |

### 3. Issue payment request

Create an invoice/payment request with classified lines. A single document is acceptable for the tenant experience, but its lines must allocate into separate internal ledgers or balance records.

### 4. Record and allocate payments

Support full and partial payment, multiple payment records, receipts, references, proof attachments, reversals, and corrections according to existing payment conventions.

Allocation order must be deterministic and visible. Prefer explicit allocation to requirement lines. Do not silently treat a general overpayment as a security deposit or last-month credit.

### 5. Evaluate readiness

Show a checklist containing at least:

- Blocking amount required.
- Blocking amount paid.
- Outstanding blocking amount.
- Each requirement's status.
- Any override and its reason.
- Whether the tenancy is ready.

### 6. Complete move-in

Only an explicit move-in action should:

- Recheck readiness inside a database transaction.
- Mark the tenancy active.
- Mark the unit occupied when allowed.
- Record actual move-in time and actor.
- Schedule the next normal invoice date.
- Avoid duplicate first invoices or requirements.

The action must be idempotent.

## Last-month rent credit lifecycle

Last-month rent is not a security deposit and must never be labeled as one.

Required behavior:

1. Collection creates a held rent-credit balance.
2. The balance is not recognized as payment of an arbitrary estimated month.
3. When notice and an actual final period are recorded, Rentwise proposes applying the credit.
4. The landlord confirms the application.
5. The final invoice consumes the available credit and shows any difference due or refundable.

### Extension example

The tenant planned to leave in December but extends through February. December and January remain normally payable. The held credit applies to February after the final period is confirmed.

### Rent increase example

- Credit held: $300
- Final monthly rent: $350
- Default result: apply $300 and collect the $50 difference.

Allow a property policy to preserve the original prepaid rate, but make the behavior explicit.

### Prorated final month example

- Credit held: $300
- Final prorated rent: $150
- Apply $150.
- Keep the other $150 as refundable/unallocated credit until settlement.

### Old debt

Do not silently redirect a last-month credit to older unpaid rent. If policy allows reallocation, require an explicit settlement decision and preserve an audit record.

### Cancellation before move-in

Unused last-month rent credit is normally refundable according to the property's cancellation policy. A non-refundable holding fee must be represented as a separate fee.

## Security-deposit lifecycle

A security deposit needs a held balance independent from rent income.

Track at least:

- Required amount.
- Collected amount.
- Currently held amount.
- Transfers in or out.
- Proposed deductions.
- Confirmed deductions.
- Refund amount.
- Refund payment/reference/date.
- Final settlement status.

Move-out flow:

1. End occupancy and capture the final billing inputs.
2. Generate final rent and utility charges.
3. Record inspection deductions with categories, notes, and optional evidence.
4. Calculate the proposed settlement.
5. Confirm the settlement.
6. Refund the remaining deposit or collect a shortfall.
7. Issue a settlement statement.
8. Mark the tenancy financially closed.

Example:

| Settlement item | Amount |
|---|---:|
| Security deposit held | $300 |
| Final electricity | -$25 |
| Lost key | -$10 |
| Refund due | $265 |

Do not automatically apply a deposit to ordinary rent while the tenancy is active.

## Overrides

If a property allows rental-level overrides:

- Show the property default and the override together.
- Require a reason.
- Record the actor and timestamp.
- Recalculate the preview before confirmation.
- Do not modify the property default.
- Do not rewrite already issued/paid requirements without a controlled adjustment or reversal.

A manager may override the move-in gate only when the property permits it. Store the remaining balance and promised payment date if supplied.

## Validation and configuration rules

- A blocking rule must have a positive required amount unless it represents another explicit readiness condition.
- Rent multipliers and percentages must be non-negative and reasonably bounded.
- Fixed amounts require a supported currency.
- Last-month credit must be refundable when unused unless an explicit, valid policy says otherwise.
- `first_period_calculation` may appear only once per tenancy.
- Auto-generation must be enabled when payment is required before move-in, unless another supported request-generation workflow exists.
- A rule cannot be both a non-refundable fee and a refundable deposit.
- Property rules apply only to new tenancy snapshots unless the landlord explicitly migrates an existing draft.
- Paid financial records must be corrected through adjustments/reversals, not destructive edits.

## Compatibility and migration

Preserve existing tenancy and invoice history.

Recommended migration approach:

1. Create the new structured property-rule and tenancy-requirement storage.
2. Convert existing settings into new defaults for future tenancies:
   - `first_month_billing_mode` -> first-period rent rule calculation.
   - `require_first_month_upfront` -> first-period rule blocking behavior.
   - `upfront_deposit_months` -> security-deposit rent-multiplier rule.
3. Treat each existing rental's `security_deposit` as its historical agreed snapshot where appropriate; do not overwrite it from the current property setting.
4. Do not retroactively generate new charges for active or ended tenancies.
5. Keep old columns temporarily if required for a safe staged rollout, but establish one canonical write path.
6. Remove or deprecate conflicting columns only after all reads and writes have moved to the new model.

## Architecture guidance

- Move invoice creation and readiness decisions out of broad `Rental::saved` side effects and into explicit application services/actions.
- Keep calculations in testable domain services.
- Use database transactions and row locking where payment allocation and activation can race.
- Make invoice/request generation, payment allocation, readiness evaluation, activation, credit application, and settlement idempotent.
- Preserve landlord scoping and existing authorization patterns.
- Preserve USD/KHR behavior and exchange-rate snapshots.
- Use enums for classifications and statuses.
- Record activity/audit history for overrides, allocations, applications, deductions, refunds, and reversals.
- Avoid embedding accounting behavior only in Filament UI callbacks; enforce it in domain/application services.

Suggested service boundaries, subject to repository conventions:

- Move-in rule calculator/resolver.
- Tenancy requirement snapshot service.
- Move-in payment request builder.
- Payment allocation service.
- Move-in readiness evaluator.
- Complete-move-in action.
- Last-month credit application service.
- Deposit settlement service.

## UI requirements

### Property Settings

Add a "Move-in requirements" section that provides:

- Preset selector.
- Dynamic ordered rule rows.
- Plain-language calculation summary.
- Warning for invalid or contradictory configurations.
- Example calculation using a sample monthly rent/start date.

Use landlord-facing language such as:

> Before move-in, collect the calculated first rent plus one month held for the tenant's final month.

### Tenancy creation

Show:

- Property defaults.
- Any permitted override.
- Calculation preview.
- Total requested.
- Total required before move-in.
- Clear distinction between rent, rent credit, refundable deposits, and fees.

### Tenancy detail

Show separate cards/balances for:

- Current rent balance.
- Last-month rent credit held.
- Security deposits held.
- Other refundable deposits.
- Outstanding move-in requirements.
- Readiness and override history.

### Move-out

Show the proposed final-period credit application and deposit settlement separately. Never merge them into one unexplained balance.

## Acceptance criteria

At minimum, automated tests must prove:

1. A property can require first-period rent plus last-month rent before move-in.
2. A $300 monthly rental produces a $600 requirement under that preset.
3. Payment allocates $300 to first rent and $300 to held last-month credit.
4. An underpaid blocking requirement prevents activation.
5. Full payment changes readiness but does not activate occupancy until the explicit move-in action.
6. The move-in action activates the rental and occupies the unit exactly once.
7. A manager override works only when authorized and records its reason.
8. Extending a tenancy does not consume the last-month credit early.
9. Final rent of $350 consumes a $300 credit and leaves $50 due under the default policy.
10. Final prorated rent of $150 consumes only $150 of a $300 credit.
11. Security deposit and last-month credit remain separate balances.
12. Deposit deductions and refunds preserve a complete settlement trail.
13. Property-setting changes do not alter an existing tenancy snapshot.
14. A rental-level override does not alter the property rule.
15. Re-running invoice/request generation does not duplicate charges.
16. Concurrent payment or activation attempts cannot double-allocate or double-activate.
17. Existing active and ended rentals are not retroactively charged during migration.
18. USD and KHR requirements retain correct currency and exchange-rate behavior.
19. Tenant invoice/portal authorization remains scoped to the correct tenant.
20. Simple-mode and full Filament flows use the same domain behavior.

## Implementation sequence

Use small, verifiable stages:

1. Add enums and structured property-rule storage.
2. Add tenancy snapshots/requirements and balances.
3. Add migration/backfill logic without changing current runtime behavior.
4. Implement calculation and preview services with tests.
5. Implement classified request generation and payment allocation.
6. Implement readiness evaluation and explicit move-in action.
7. Switch the Property Settings and tenancy UIs to the new services.
8. Implement last-month credit application.
9. Implement deposit settlement and refunds.
10. Remove or deprecate conflicting legacy behavior after compatibility tests pass.
11. Run the relevant test suite and `graphify update .` after code changes.

## Out of scope for the planning task

This document does not authorize immediate application-code changes. The implementing AI should first inspect the referenced code, propose its concrete schema and file-change plan, and check for unrelated working-tree changes before editing.

The implementing AI must not discard unrelated user changes.
