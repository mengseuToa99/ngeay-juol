# Task 09: Replace Default README With Real Project Docs

## Goal

Replace the default Laravel README with useful RentWise setup, development, operations, and deployment documentation.

## Current State

- `README.md` is still the default Laravel framework README.
- `docs/SUBSCRIPTIONS.md` documents the platform subscription module, but some docs describe planned behavior that is not fully implemented.
- The project has several important runtime requirements: Laravel, Filament, Spatie packages, Dompdf, Browsershot/Chrome, scheduler, queue, Khmer fonts, and locale support.

## Main Gap

Another developer or deployment AI cannot reliably set up, run, verify, or operate the app from the current README.

## Files To Inspect First

- `README.md`
- `composer.json`
- `package.json`
- `.env.example`
- `routes/console.php`
- `config/services.php`
- `config/dompdf.php`
- `docs/SUBSCRIPTIONS.md`
- `database/seeders`
- `app/Providers/Filament`

## Required README Sections

1. Project overview
2. Feature overview
3. Tech stack
4. Local setup
5. Environment variables
6. Database migration and seed instructions
7. Running the app
8. Running tests
9. Scheduler and queue setup
10. PDF generation requirements
11. Khmer localization notes
12. User roles and panels
13. Common commands
14. Troubleshooting
15. Known incomplete modules / roadmap

## Important Notes To Include

- Browsershot/Chrome is preferred for invoice PDFs because Dompdf does not shape Khmer correctly.
- `config/services.php` contains Browsershot configuration.
- Scheduler should run `php artisan schedule:run`.
- Tenant portal uses username login through shared login flow.
- Landlord/admin work happens through Filament panels.
- Platform subscription billing is separate from tenant rent billing.

## Suggested Extra Docs

Create these if helpful:

- `docs/LOCAL_SETUP.md`
- `docs/DEPLOYMENT.md`
- `docs/PDF_RENDERING.md`
- `docs/LOCALIZATION.md`
- `docs/ROADMAP.md`

## Acceptance Checks

- `README.md` no longer contains generic Laravel marketing text.
- A fresh developer can install dependencies, configure `.env`, migrate/seed, run the dev server, run tests, and understand cron/queue requirements.
- Docs clearly distinguish implemented features from planned/incomplete features.
- Docs mention the PDF Khmer rendering caveat and Chrome/Browsershot requirement.

