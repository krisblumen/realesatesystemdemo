# GEMINI.md

This file provides instructional context for Gemini CLI when working in the **newhauz** repository.

## Project Overview

**newhauz** is a modern real estate platform built as a **Modern Monolith** with Laravel. It prioritizes SEO and developer velocity by keeping the frontend and backend in a single integrated repository.

- **Backend**: Laravel 13.x (PHP 8.3+)
- **Frontend**: Blade + Livewire v3, Vite 8, Tailwind CSS v4
- **Database**: PostgreSQL with PostGIS extension (Required)
- **Admin Panel**: Filament v3
- **Testing**: PHPUnit 12

## Building and Running

### Initial Setup
```bash
composer setup
```
*Note: Ensure Docker is running for PostgreSQL/PostGIS.*

### Development
```bash
composer dev
```

## Development Conventions

### Coding Style
- **PHP**: PSR-12 via Laravel Pint (4-space indentation).
- **Livewire**: Describe state with public properties; keep logic in `render()` or dedicated actions.
- **Blade**: Use kebab-case for component filenames.

### Naming Conventions
- **Classes/Models**: `PascalCase`
- **Methods/Variables**: `camelCase`
- **Database**: `snake_case`

### Architectural Guidelines
- **Modern Monolith**: Avoid separate frontend repositories. Use Livewire for interactivity.
- **Data Safety**: All geolocation logic must leverage PostGIS via Eloquent.
- **Security**: Use Spatie Laravel Permission for Role-Based Access Control (RBAC).

## Project Structure
- `app/Livewire/`: Interactive UI components.
- `app/Filament/`: Admin panel resources.
- `resources/views/`: Blade templates and layouts.
- `database/migrations/`: Schema definitions (PostgreSQL compatible).
