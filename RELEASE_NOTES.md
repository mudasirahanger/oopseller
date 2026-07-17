# OopSeller 0.3.1

## Composer compatibility fix

- Updated `laravel/tinker` from 2.x to 3.x for Laravel 13 compatibility.
- Aligned Laravel development dependencies with the Laravel 13 application skeleton.
- Keeps PHP 8.5, SQLite, and manual macOS development support.

# OopSeller Manual macOS Development Release

Release date: 2026-07-17

This release merges the latest repaired application code and changes the default development environment from Docker/MySQL/Redis to a manual macOS workflow using PHP 8.5, Node.js 23, and SQLite.

## Included repairs

- Correct Laravel dependency installation instructions for the missing `vendor/autoload.php` error
- Working database-backed client and product creation
- Removal of fake seller, product, ranking, advertising, and competitor metrics
- Amazon LWA authorization and SP-API integration boundaries
- Catalog Items, Listings Items, Sellers, and Product Type Definitions support
- Listing validation preview before publishing
- SQLite-first local environment
- File cache, file sessions, synchronous queues, local storage, and log mail
- macOS environment doctor, setup, start, reset, and verification scripts
- Public npm registry URLs in the lockfile
- Optional Docker/MySQL/Redis environment retained separately

## First run

```bash
chmod +x scripts/*.sh
./scripts/setup-macos.sh
./scripts/start-dev.sh
```

## v4
- Fixed Laravel 13 scheduled callback discovery by assigning unique names before `withoutOverlapping()`.
- Disabled Horizon snapshot scheduling unless the active queue driver is Redis.
