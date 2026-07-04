# Roadmap And Incomplete Modules

This document lists known gaps so future work does not confuse planned behavior with implemented behavior.

## Subscription Billing

Implemented:

- Subscription plans, subscriptions, subscription histories, and subscription payments.
- Staff resources for subscription management.
- Landlord panel middleware for subscription access enforcement.
- Scheduled `subscriptions:process --sweep --recompute --dunning`.

Incomplete or limited:

- Dunning currently reports counts in the command output; real notification delivery is not wired.
- Payment gateway automation is not implemented.
- Auto-renewal with gateway charging is not implemented.
- Some behavior described in `docs/SUBSCRIPTIONS.md` is design intent and should be verified against code before relying on it operationally.

## Tenant Portal

Implemented:

- Shared login redirects tenants to `/portal`.
- Tenant invoice dashboard and invoice detail view.

Incomplete or limited:

- Portal is read-only.
- Online rent payment is not wired.

## PDFs

Implemented:

- Browsershot invoice PDF rendering.
- Dompdf fallback.
- A4, A5, and thermal receipt support.

Known limitation:

- Dompdf fallback does not shape Khmer correctly. Chrome/Browsershot is required for production-quality Khmer invoices.

## Notifications

Implemented:

- Queue-ready Laravel foundation.
- Subscription dunning command structure.

Incomplete:

- User-facing notification delivery and templates need to be completed.

## Operations

Recommended future work:

- Add deployment-specific health checks.
- Add automated PDF smoke tests where Chrome is available.
- Add recurring queue and scheduler monitoring.
- Document production backup and restore procedures after the target database engine is finalized.
