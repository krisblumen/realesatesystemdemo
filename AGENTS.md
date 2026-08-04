# Repository Guidelines

## Project Structure & Module Organization

This repository is a Laravel 13 application running on PHP 8.3. Application code lives in `app/`: HTTP controllers belong in `app/Http/Controllers`, Eloquent models in `app/Models`, and framework bootstrapping in `app/Providers`. Define browser routes in `routes/web.php` and console commands in `routes/console.php`.

Frontend source files are under `resources/css`, `resources/js`, and `resources/views`; Vite publishes compiled assets to `public/build`. Database migrations, factories, and seeders live in `database/`. Keep automated tests in `tests/Feature` for behavior crossing application boundaries and `tests/Unit` for isolated logic.

## Build, Test, and Development Commands

- `composer setup` — install dependencies, create `.env`, generate the app key, migrate the database, and build frontend assets.
- `composer dev` — run the Laravel server, queue listener, log viewer, and Vite development server together.
- `composer test` — clear cached configuration and run the complete PHPUnit suite.
- `npm run dev` — start Vite only for frontend development.
- `npm run build` — create production-ready frontend assets.
- `./vendor/bin/pint` — format PHP code using Laravel Pint.

## Coding Style & Naming Conventions

Follow PSR-12 and Laravel conventions, using four spaces for PHP indentation. Use `PascalCase` for classes, `camelCase` for methods and variables, and `snake_case` for database columns. Name migrations descriptively, for example `create_properties_table`. Blade templates should use lowercase kebab-case filenames when names contain multiple words. Keep controllers thin; place domain behavior in focused services or models rather than route closures.

## Testing Guidelines

The project uses PHPUnit 12 through Laravel's test runner. Name test files with the `Test.php` suffix and write test methods that describe observable behavior. Add a regression test for every bug fix and cover both successful and invalid-input paths. Run `composer test` before opening a pull request; use `php artisan test --filter=TestName` for focused feedback.

## Git Flow

| Branch | Purpose |
| --- | --- |
| `main` | Production — only receives merges from `release/*` or hotfixes |
| `develop` | Integration — all feature branches merge here first |
| `feature/rfc-NNN-name` | One branch per RFC or feature |
| `fix/bug-NNN-name` | Bug fixes branched from `develop` |
| `release/vX.X.X` | Release candidates before merging to `main` |

**Workflow per RFC:**
1. Branch from `develop`: `git checkout -b feature/rfc-NNN-name develop`
2. Develop and commit using Conventional Commits.
3. Run `composer test` — all tests must pass.
4. Open a Pull Request targeting `develop`.
5. QA validates against the RFC definition of done.
6. Merge to `develop` after approval.
7. Cut a `release/vX.X.X` branch when ready for production, then merge to `main`.

## Commit & Pull Request Guidelines

Use Conventional Commits consistently: `feat: add property search`, `fix: validate listing price`, or `test: cover user registration`. Keep commits small and independently verifiable. Pull requests should explain the problem and solution, link the relevant issue, list verification commands, and include screenshots for visible UI changes. Never add AI attribution or `Co-Authored-By` trailers.

## Security & Configuration

Do not commit secrets or environment-specific values. Add new variables to `.env.example`, validate configuration defaults, and use migrations or seeders instead of sharing local database files.
