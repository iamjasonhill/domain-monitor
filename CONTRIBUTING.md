# Contributing

Thanks for helping improve Domain Monitor.

## Development setup

- PHP 8.2+
- Composer
- Node 20+

From the project root:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
```

## Run the quality gates (same as CI)

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=2G
php artisan test --stop-on-failure
```

## Before opening a PR

- Keep changes small and focused.
- Add/adjust tests when behavior changes.
- Update docs if you add env vars, scheduled tasks, or new settings UI.

## Useful commands

- Local dev: `composer dev`
- Run a single test file:

```bash
php artisan test tests/Feature/SomeTest.php
```


