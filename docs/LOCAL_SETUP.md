# Local Setup

This guide assumes PHP 8.2+, Composer, Node, npm, and SQLite are available.

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
```

The default seed creates roles, permissions, and a super admin account:

- `admin@rentwise.test`
- `password`

Set `RENTWISE_ADMIN_EMAIL` and `RENTWISE_ADMIN_PASSWORD` before seeding if you want different credentials.

## Demo Data

Full demo data:

```bash
SEED_DEMO=true php artisan migrate:fresh --seed
```

This creates a landlord, tenant, property, utility rates, reading, invoice, and partial payment.

Portfolio demo only:

```bash
php artisan db:seed --class=Database\\Seeders\\LandlordPortfolioSeeder
```

This creates a landlord portfolio with multiple properties, rooms, and sequential tenant histories.

## Development Server

Preferred:

```bash
composer run dev
```

This runs the Laravel server, database queue listener, Pail logs, and Vite.

Manual alternative:

```bash
php artisan serve
npm run dev
php artisan queue:listen --tries=1 --timeout=0
```

## Access

- Shared login: `/login`
- Staff panel: `/admin`
- Landlord panel: `/landlord`
- Tenant portal: `/portal`

The shared login form accepts email or username. Tenants normally use a generated room username, but email also works when present.

## Tests

```bash
composer test
```

The Composer test script clears config first, then runs `php artisan test`.
