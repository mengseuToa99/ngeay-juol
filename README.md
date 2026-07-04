# RentWise

## Project Overview

RentWise is a Laravel and Filament property-management app for Cambodian rental operators. It manages landlord portfolios, rooms, tenants, rent invoices, utility readings, payments, invoice documents, and a small tenant portal. The app also includes a separate platform subscription module for billing landlords for access to RentWise.

The public product name in the UI is currently `ngeay juol`.

## Feature Overview

- Staff back office at `/admin` for platform users, landlord management, users, subscription plans, subscriptions, subscription payments, and system settings.
- Landlord back office at `/landlord` for properties, rooms, rentals, invoices, payments, utility pricing, utility readings, waivers, dashboards, monthly billing, and property settings.
- Tenant portal at `/portal` for read-only invoice access. Tenants authenticate through the shared `/login` page with username or email.
- Rent billing and utility billing for landlord-to-tenant invoices.
- Platform subscription billing for platform-to-landlord access. This is separate from tenant rent billing.
- PDF invoice generation for A4, A5, and thermal receipt formats, with Excel export support.
- English and Khmer UI support with Khmer invoice fonts.
- Spatie roles and permissions through Filament Shield.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Filament 3
- Laravel Fortify and Sanctum
- Spatie Permission, Activitylog, Medialibrary, Browsershot
- Filament Shield and Filament Medialibrary plugin
- barryvdh/laravel-dompdf fallback renderer
- Vite 7 and Tailwind CSS 4
- Puppeteer / headless Chrome for preferred PDF rendering
- PHPUnit 11

## Local Setup

Install PHP dependencies:

```bash
composer install
```

Install Node dependencies:

```bash
npm install
```

Create an environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

The default `.env.example` uses SQLite. Create the database file before migrating:

```bash
touch database/database.sqlite
```

Run migrations and base seeders:

```bash
php artisan migrate --seed
```

Build frontend assets once:

```bash
npm run build
```

For a fuller walkthrough, see [docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md).

## Environment Variables

Important local and deployment settings:

```dotenv
APP_NAME="ngeay juol"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

RENTWISE_ADMIN_EMAIL=admin@rentwise.test
RENTWISE_ADMIN_PASSWORD=password
SEED_DEMO=false

BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
# BROWSERSHOT_NODE_BINARY=/usr/bin/node
# BROWSERSHOT_NPM_BINARY=/usr/bin/npm
# BROWSERSHOT_NODE_MODULE_PATH=/absolute/path/to/node_modules
```

`config/services.php` contains the Browsershot configuration used by invoice PDFs. Keep `BROWSERSHOT_CHROME_PATH` accurate on each server.

## Database And Seeds

Base seed:

```bash
php artisan migrate --seed
```

This creates roles, permissions, and a super admin user. Defaults are:

- Email: `admin@rentwise.test`
- Password: `password`

Override them with `RENTWISE_ADMIN_EMAIL` and `RENTWISE_ADMIN_PASSWORD`.

Optional demo data:

```bash
SEED_DEMO=true php artisan migrate:fresh --seed
```

Demo accounts include:

- Landlord: `landlord@rentwise.test` / `password`
- Tenant: `tenant` or `tenant@rentwise.test` / `password`

Portfolio-only demo seeder:

```bash
php artisan db:seed --class=Database\\Seeders\\LandlordPortfolioSeeder
```

## Running The App

Run the full local development stack:

```bash
composer run dev
```

That starts Laravel, the database queue listener, Laravel Pail logs, and Vite.

Or run the pieces separately:

```bash
php artisan serve
npm run dev
php artisan queue:listen --tries=1 --timeout=0
```

Main URLs:

- `/login` shared login for staff, landlords, managers, and tenants
- `/admin` platform staff panel
- `/landlord` landlord panel
- `/portal` tenant portal

## Running Tests

```bash
composer test
```

Equivalent command:

```bash
php artisan config:clear
php artisan test
```

## Scheduler And Queue

Production needs Laravel's scheduler running once per minute:

```cron
* * * * * cd /path/to/rentwise && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands in `routes/console.php`:

- `rentals:update-statuses` daily at `00:30`
- `invoices:generate-rent` monthly on day 1 at `02:00`
- `subscriptions:process --sweep --recompute --dunning` daily at `00:30`

The default queue driver is `database`. Run a worker in production:

```bash
php artisan queue:work --tries=3
```

If you change queue connection, update `.env` and your process supervisor accordingly.

## PDF Generation

Invoice PDFs prefer Browsershot and headless Chrome because Dompdf does not shape Khmer text correctly. Dompdf remains a fallback in `App\Services\InvoicePdfService`, but Khmer output may be visually wrong when the fallback is used.

Required for reliable invoice PDFs:

- Google Chrome or Chromium installed on the server
- Node and npm available to Browsershot
- `npm install` run so Puppeteer is installed
- `BROWSERSHOT_CHROME_PATH` set to the Chrome binary
- Writable `storage/` and `bootstrap/cache/`

More detail: [docs/PDF_RENDERING.md](docs/PDF_RENDERING.md).

## Khmer Localization

Supported locales are `en` and `km`. The locale switcher stores the user's selection in session and a one-year `locale` cookie. Translation strings are in `lang/km.json`.

Khmer fonts are included under `resources/fonts/` and cached Dompdf font files may exist under `storage/fonts/`. Chrome/Browsershot is still the preferred path for Khmer invoice PDF rendering.

More detail: [docs/LOCALIZATION.md](docs/LOCALIZATION.md).

## User Roles And Panels

Seeded roles:

- `super_admin`: platform staff with full access.
- `support`: platform staff with read-focused support access and selected user-management permissions.
- `landlord`: owner role for landlord-managed resources.
- `landlord_manager`: delegated landlord staff role with fewer destructive permissions.
- `tenant`: portal-only role.

Panel routing:

- Platform staff use `/admin`.
- Landlords and landlord managers use `/landlord`.
- Tenants use `/portal`.

The shared login flow redirects users based on role. Tenants can log in with username or email.

## Common Commands

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=Database\\Seeders\\LandlordPortfolioSeeder
php artisan shield:generate --all
php artisan rentals:update-statuses
php artisan invoices:generate-rent
php artisan invoices:generate-rent --date=2026-07-01
php artisan subscriptions:process
php artisan subscriptions:process --sweep --recompute --dunning
php artisan units:sync-occupancy
php artisan queue:work --tries=3
php artisan schedule:run
npm run dev
npm run build
composer test
```

## Troubleshooting

- Login redirects to the wrong place: confirm the user's role and `status` are set. Only active users can log in.
- `/landlord` denies access: confirm the landlord has an active platform subscription or is still inside the configured grace/read-only period.
- Tenant cannot log in: use the shared `/login` page and the tenant username/email. The old separate portal login view has been removed.
- PDF generation fails: verify Chrome exists at `BROWSERSHOT_CHROME_PATH`, Node/npm are available, `npm install` has run, and `storage/` is writable.
- Khmer text is broken in PDFs: confirm Browsershot is being used. Dompdf fallback cannot shape Khmer correctly.
- Queued or scheduled work does not run: start a queue worker and install the scheduler cron.
- SQLite local setup fails: create `database/database.sqlite` before running migrations.
- Filament permission changes do not appear: clear permission/config caches and regenerate Shield permissions if resources changed.

## Known Incomplete Modules / Roadmap

- Subscription lifecycle exists, but dunning currently logs counts; real notification delivery is not fully wired.
- Platform subscription payment gateway integration is not implemented; payments are recorded through internal resources.
- `docs/SUBSCRIPTIONS.md` includes intended subscription behavior and schema details. Treat it as a design and implementation reference, not a guarantee that every workflow is complete.
- Tenant portal is intentionally limited to read-only invoice access.
- Dompdf is retained only as fallback because Khmer shaping requires Chrome/Browsershot.

See [docs/ROADMAP.md](docs/ROADMAP.md) and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
