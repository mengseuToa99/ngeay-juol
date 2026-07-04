# Deployment

RentWise is a standard Laravel app with extra operational requirements for queues, scheduler, Chrome/Browsershot, writable storage, and built frontend assets.

## Server Requirements

- PHP 8.2+
- Composer
- Node and npm
- Database supported by Laravel
- Google Chrome or Chromium
- Supervisor or another process manager for queue workers
- Cron for Laravel scheduler
- Writable `storage/` and `bootstrap/cache/`

## Build Steps

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run the base seed only when bootstrapping a new environment:

```bash
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force
```

Set `RENTWISE_ADMIN_EMAIL` and `RENTWISE_ADMIN_PASSWORD` before seeding.

## Environment

Production should set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
```

Use a real mailer before enabling notification workflows. The default local mailer logs messages.

## Queue

Run a queue worker under Supervisor or systemd:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Restart workers after deployments:

```bash
php artisan queue:restart
```

## Scheduler

Install one cron entry:

```cron
* * * * * cd /path/to/rentwise && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler handles rental status updates, monthly rent invoice generation, and subscription lifecycle processing.

## Storage

Create the public storage link if public uploads are used:

```bash
php artisan storage:link
```

Ensure the web user can write to:

- `storage/`
- `bootstrap/cache/`

## PDF Rendering

Install Chrome/Chromium and keep `BROWSERSHOT_CHROME_PATH` accurate. Invoice PDFs rely on Browsershot for correct Khmer shaping and use Dompdf only as a fallback.
