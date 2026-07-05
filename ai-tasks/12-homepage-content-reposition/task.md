# Task: Reposition Public Homepage Content Away From Billing And Fake Metrics

## Assignee

Other AI implementation task.

## Context

The public homepage is:

- `http://127.0.0.1:8000/`

Main file:

- `resources/views/welcome.blade.php`

Localization file:

- `lang/km.json`

Current homepage has too much SaaS/pricing/billing emphasis and includes unsupported customer-style numbers:

- pricing navigation and pricing section
- fake metric cards like `2,400+`, `$1.2M+`, and `99.8%`
- testimonial names and quantified claims
- billing-heavy hero copy
- CTA text like `Join thousands of landlords...`

The user does not want billing/pricing content or customer-number claims on the public page. Reposition the page as a practical product overview for Cambodian rental operations.

## Goal

Rewrite and lightly restructure `/` so it explains what ngeay juol does for admins, landlords, and tenants without pricing tables, fake customer counts, or heavy billing language.

The page should feel practical, local, and trustworthy:

- property/room operations
- tenant records
- utility readings
- maintenance requests
- receipts/documents where relevant
- Khmer and English support
- role-specific access for admin, landlord, and tenant

## Required Removals

Remove or replace these parts:

1. Pricing navigation
   - Remove `Pricing` from desktop and mobile nav.
   - Replace with a better anchor such as `How it works`, `Roles`, or `Support`.

2. Pricing section
   - Remove the full pricing cards section.
   - Do not show dollar plan prices on the public homepage.

3. Fake metric/stat cards
   - Remove the hero stat cards with `2,400+`, `$1.2M+`, and `99.8%`.
   - Do not replace with other unverifiable customer numbers.

4. Customer testimonials with names/numbers
   - Remove named testimonials unless they are real and verified.
   - Remove claims like `50 units`, `5 minutes`, `40% reduction`, and specific customer names.

5. Billing-heavy claims
   - Reduce wording that makes billing/invoicing the whole product.
   - Do not remove all invoice/receipt mentions, because receipts are a real feature; just do not make billing the main homepage story.

6. Unsupported scale claims
   - Remove `Join thousands of landlords...`.

## Proposed Page Structure

### 1. Header Navigation

Recommended nav:

- `Features`
- `How it works`
- `Roles`
- `Support`

Keep:

- logo
- language switcher
- dark/light toggle
- login button
- main CTA if still needed

Avoid:

- `Pricing`

### 2. Hero Section

Replace hero with a more grounded message.

Recommended title:

```text
Rental management made simple for Cambodian landlords
```

Recommended subtitle:

```text
Manage rooms, tenants, utilities, maintenance, documents, and tenant access in one Khmer-ready workspace.
```

Recommended badge:

```text
Built for local rental operations
```

Recommended buttons:

- `Sign In`
- `Explore Features`

If keeping `Start Free`, make sure it links to a real flow. If `/link` is not production-ready, prefer `Sign In` and `Contact Support`.

### 3. Replace Hero Stats With Role Cards

Replace the three fake metric cards with three role cards:

1. `For admins`
   - `Manage landlords, plans, support settings, and platform access.`

2. `For landlords`
   - `Track properties, rooms, tenants, utilities, payments, and maintenance from one workspace.`

3. `For tenants`
   - `Log in to view room details, invoices, payment records, and maintenance updates.`

These cards should be visual, not numeric.

### 4. Features Section

Keep a feature grid, but adjust content away from billing-first copy.

Recommended feature cards:

1. `Property and room records`
   - `Keep each building, room, rent amount, occupancy status, and tenant history organized.`

2. `Tenant move-in workflow`
   - `Create tenant records, assign rooms, store contact details, and generate login access when needed.`

3. `Utility reading tracking`
   - `Record water and electricity readings clearly, with history per room and property.`

4. `Payments and receipts`
   - `Track payments and produce clean records for landlords and tenants without relying on spreadsheets.`

5. `Maintenance requests`
   - `Let tenants report room issues and keep landlord replies, status, and photos in one thread.`

6. `Khmer and English workspace`
   - `Switch between Khmer and English for staff, landlords, and tenant-facing screens.`

### 5. How It Works Section

Replace the testimonial section with a workflow section.

Recommended title:

```text
How ngeay juol fits daily rental work
```

Recommended steps:

1. `Add properties and rooms`
2. `Move tenants into rooms`
3. `Record utilities and payments`
4. `Handle maintenance requests`
5. `Share tenant portal access`
6. `Review everything from dashboards`

Use cards or a horizontal timeline.

### 6. Roles Section

Replace the pricing section with a roles/use-cases section.

Recommended title:

```text
One workspace for every rental role
```

Recommended cards:

1. `Platform admin`
   - `Configure landlords, subscriptions, settings, language, and support workflows.`

2. `Landlord or manager`
   - `Run day-to-day property operations without jumping between spreadsheets and chat threads.`

3. `Tenant`
   - `Access room records, invoice history, payment details, and maintenance updates from the portal.`

Optional use-case cards:

- `Apartment buildings`
- `Room rentals`
- `Shop-house units`
- `Multi-property landlords`

Do not include plan prices.

### 7. Final CTA

Replace the current CTA with:

Recommended title:

```text
Start managing your rental workspace
```

Recommended body:

```text
Sign in to manage properties, tenants, utilities, maintenance, and tenant access from one place.
```

Recommended buttons:

- `Sign In`
- `Contact Support`

Do not use:

- `Join thousands...`
- customer-count claims
- revenue claims

## Visual Direction

Preserve the existing brand feel:

- emerald/teal accent
- logo asset
- glass header
- rounded cards
- light/dark support
- responsive mobile layout

Improve the page by making content feel more operational:

- role cards instead of stats
- workflow cards instead of testimonials
- use-case cards instead of pricing
- clear CTAs for login/support

Avoid a generic SaaS pricing page.

## Localization Requirements

All new visible strings must use `__()`.

Add Khmer translations for new strings in:

- `lang/km.json`

This is not optional. The task is incomplete if the homepage works in English but new strings appear untranslated in Khmer.

Be careful editing `lang/km.json`; inspect nearby keys first and keep valid JSON.

Use existing translation style:

- English brand: `ngeay juol`
- Khmer brand: `ងាយជួល`

Minimum new strings that likely need Khmer entries:

- `Built for local rental operations`
- `Rental management made simple for Cambodian landlords`
- `Manage rooms, tenants, utilities, maintenance, documents, and tenant access in one Khmer-ready workspace.`
- `For admins`
- `For landlords`
- `For tenants`
- `Manage landlords, plans, support settings, and platform access.`
- `Track properties, rooms, tenants, utilities, payments, and maintenance from one workspace.`
- `Log in to view room details, invoices, payment records, and maintenance updates.`
- `Property and room records`
- `Tenant move-in workflow`
- `Utility reading tracking`
- `Payments and receipts`
- `Maintenance requests`
- `Khmer and English workspace`
- `How ngeay juol fits daily rental work`
- `Add properties and rooms`
- `Move tenants into rooms`
- `Record utilities and payments`
- `Handle maintenance requests`
- `Share tenant portal access`
- `Review everything from dashboards`
- `One workspace for every rental role`
- `Platform admin`
- `Landlord or manager`
- `Start managing your rental workspace`
- `Sign in to manage properties, tenants, utilities, maintenance, and tenant access from one place.`

The implementer may adjust exact English copy, but every final visible string must have a Khmer translation.

## Files To Edit

Expected:

- `resources/views/welcome.blade.php`
- `lang/km.json`

Optional if needed:

- `resources/css/app.css`

Do not edit backend billing/subscription logic for this task.

## Verification

Run:

```bash
php -l resources/views/welcome.blade.php
python3 -m json.tool lang/km.json >/tmp/rentwise-km-json-check.json
rg -n "__\\(['\\\"][^'\\\"]+['\\\"]\\)" resources/views/welcome.blade.php
php artisan route:list --path=/
```

If route-list fails because the local database is unavailable, report the exact blocker and still run the syntax/JSON checks.

Manual browser checks:

- `http://127.0.0.1:8000/` loads.
- Desktop header no longer shows `Pricing`.
- Mobile menu no longer shows `Pricing`.
- No fake numbers remain in the hero.
- No pricing cards remain.
- No named fake testimonials remain.
- Khmer locale renders translated homepage strings.
- No new homepage text is hardcoded outside `__()` unless it is a brand name, URL, or technical constant.

## Acceptance Criteria

- Homepage no longer focuses on billing/pricing.
- Homepage no longer includes unsupported customer counts, revenue counts, or percentage claims.
- Pricing section is replaced with role/use-case content.
- Testimonials are replaced with workflow or trust content that does not invent customers.
- Hero explains practical rental operations.
- New strings are wrapped in `__()` and localized in Khmer.
- `lang/km.json` remains valid JSON.
- Page remains responsive on desktop and mobile.
