# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **Backend**: Laravel 13.x, PHP 8.3+
- **Frontend**: Vite 8, Tailwind CSS v4, vanilla JS (no React/Vue)
- **Database**: PostgreSQL with the PostGIS extension. Spatial features use `clickbar/laravel-magellan` ^2.2 (PostGIS functions like `ST_Contains`, `ST_Centroid`, `ST_AsGeoJSON`, `geometry(Polygon,4326)` columns, and GIST indexes — see `app/Models/Zone.php`). Set via `DB_CONNECTION=pgsql` in `.env`. Note: Laravel's framework fallback in `config/database.php` is still `sqlite`, but the app always runs on `pgsql` — these PostGIS features do NOT work on SQLite.
- **Testing**: PHPUnit 12 against a live PostgreSQL+PostGIS instance (database `inmo_test`, see `phpunit.xml`)
- **Admin panel**: Filament ^3.2 (with Spatie Media Library plugin)
- **Permissions/Media**: `spatie/laravel-permission` ^8.0, `spatie/laravel-medialibrary` ^11.23
- **Font**: Bunny-served "Instrument Sans" via `laravel-vite-plugin/fonts`

## Commands

> **Prerequisite**: a running PostgreSQL instance with the PostGIS extension is
> required for both development and testing. The default dev database uses the
> `pgsql` connection from `.env`; the test suite connects to `inmo_test` (host
> `127.0.0.1:5432`, user `postgres`). Migrations create PostGIS geometry columns
> and GIST indexes, so the extension must be enabled before migrating.

```bash
# Initial setup (install deps, generate key, migrate, build assets)
composer setup

# Start all dev servers concurrently (PHP, queue, log tail, Vite HMR)
composer dev

# Run all tests (clears config cache first)
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run tests matching a filter
php artisan test --filter=ExampleTest

# Build frontend assets
npm run build

# Fix PHP code style (Laravel Pint)
./vendor/bin/pint

# Lint without writing
./vendor/bin/pint --test
```

## Architecture

Standard Laravel MVC. Currently a minimal skeleton — no auth starter kit, no service layer, no additional packages beyond the defaults.

- `app/Http/Controllers/` — HTTP controllers (only base `Controller.php` exists)
- `app/Models/` — Eloquent models (`User.php` default)
- `app/Providers/` — Service providers (`AppServiceProvider.php`)
- `resources/views/` — Blade templates (only `welcome.blade.php` exists)
- `resources/css/app.css` + `resources/js/app.js` — Vite entrypoints
- `routes/web.php` — All web routes; `routes/console.php` for Artisan commands
- `database/migrations/` — Schema migrations (users, cache, jobs tables included)

## Testing

Tests run against PostgreSQL+PostGIS, not SQLite. `phpunit.xml` forces `DB_CONNECTION=pgsql` and `DB_DATABASE=inmo_test` (host `127.0.0.1`, port `5432`, user `postgres`), overriding any `.env` setting. A live Postgres instance with PostGIS enabled and the `inmo_test` database is required before running the suite — the spatial functions used by `app/Models/Zone.php` (`ST_Contains`, `ST_Centroid`, `ST_AsGeoJSON`, etc.) cannot run on SQLite. The `Feature` suite hits the full HTTP stack; `Unit` tests are pure PHP.

## Code style

PHP follows PSR-12 enforced by Laravel Pint. Indentation: 4 spaces (PHP, JS), 2 spaces (YAML). Run Pint before committing PHP changes.
