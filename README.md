# Sparko

Korean Laravel Spark: a subscription billing portal powered by PortOne V2.

## Requirements

- PHP ^8.2
- Laravel ^12.0 or ^13.0
- `cable8mm/laravel-portone` ^0.8.0

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Configure PortOne credentials and the billable model in the host application before charging.

## Checks

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
npm run build
```

## Plans and discounts

Plan entries are defined in `config/plans.php`. Promotion codes are defined in `config/discounts.php`.

The authoritative requirements are in `.replworks/PRODUCT_SPEC.md`, `.replworks/TECH_STACK.md`, `.replworks/ARCHITECTURE.md`, and `.replworks/TASKS.md`.
