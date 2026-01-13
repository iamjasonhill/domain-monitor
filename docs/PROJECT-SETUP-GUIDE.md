# Project Setup Guide - Laravel 12 + Livewire + Tailwind

This document contains the complete technical setup configuration for replicating this project's development environment, tools, and preferences in new Laravel projects.

## Quick Start

Copy this entire document to your new project and follow the setup steps. All configuration files and their contents are included below.

---

## Core Versions

### PHP & Laravel
- **PHP**: 8.2+ (project uses 8.4.11)
- **Laravel Framework**: ^12.0
- **Laravel Structure**: Laravel 12 streamlined structure (no Kernel.php, middleware in bootstrap/app.php)

### Key PHP Packages

Add to `composer.json`:

```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "laravel/horizon": "^5.40",
    "laravel/tinker": "^2.10.1",
    "livewire/livewire": "^3.6.4",
    "livewire/volt": "^1.7.0"
  },
  "require-dev": {
    "fakerphp/faker": "^1.23",
    "larastan/larastan": "^3.8",
    "laravel/boost": "^1.8",
    "laravel/breeze": "^2.3",
    "laravel/pail": "^1.2.2",
    "laravel/pint": "^1.24",
    "laravel/sail": "^1.41",
    "mockery/mockery": "^1.6",
    "nunomaduro/collision": "^8.6",
    "phpstan/phpstan": "^2.1",
    "phpunit/phpunit": "^11.5.3"
  }
}
```

### NPM Packages

Add to `package.json`:

```json
{
  "devDependencies": {
    "@tailwindcss/forms": "^0.5.2",
    "@tailwindcss/vite": "^4.0.0",
    "autoprefixer": "^10.4.2",
    "axios": "^1.11.0",
    "concurrently": "^9.0.1",
    "laravel-vite-plugin": "^2.0.0",
    "postcss": "^8.4.31",
    "tailwindcss": "^3.1.0",
    "vite": "^7.0.7"
  }
}
```

---

## Installation Steps

### 1. Install PHP Dependencies

```bash
composer install
```

### 2. Install Laravel Breeze with Livewire

```bash
composer require laravel/breeze --dev
php artisan breeze:install livewire
```

### 3. Install Laravel Boost

```bash
composer require laravel/boost --dev
```

### 4. Install NPM Dependencies

```bash
npm install
```

---

## Configuration Files

### 1. Pint Configuration

**File**: `pint.json`

```json
{
    "preset": "laravel",
    "rules": {
        "simplified_null_return": true,
        "braces": {
            "position_after_control_structures": "same"
        }
    }
}
```

### 2. PHPStan Configuration

**File**: `phpstan.neon`

```yaml
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - config
        - database/factories
        - database/seeders
    
    # Level 7 (high strictness) - appropriate for new projects
    # Level 8 is maximum strictness if you want even more checks
    level: 7
    
    # Cache directory for performance
    tmpDir: storage/phpstan
    
    # Ignore errors from third-party code
    excludePaths:
        - vendor/
        - bootstrap/cache/
        - storage/
        - public/
```

### 3. PHPUnit Configuration

**File**: `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 4. Tailwind Configuration

**File**: `tailwind.config.js`

```javascript
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
```

### 5. PostCSS Configuration

**File**: `postcss.config.js`

```javascript
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

### 6. Vite Configuration

**File**: `vite.config.js`

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

### 7. CSS Entry Point

**File**: `resources/css/app.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### 8. JavaScript Entry Point

**File**: `resources/js/app.js`

```javascript
import './bootstrap';
```

### 9. Bootstrap JavaScript

**File**: `resources/js/bootstrap.js`

```javascript
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

---

## Cursor AI Configuration

### 1. Cursor Rules

**File**: `.cursor/rules/laravel-boost.mdc`

Create the directory and file:

```markdown
---
alwaysApply: true
---
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.11
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).

=== livewire/preference rules ===

## Livewire Preference

- **Default**: Use Livewire for all interactive components and frontend interactions.
- **Exception**: Only use other frameworks (Inertia, Vue, React, etc.) if explicit permission is sought from the user.
- **Rationale**: Less JavaScript needed, simpler form handling, real-time updates without page refresh, built-in pagination, familiar PHP syntax.
- When building new features, default to Livewire components unless the user specifically requests an alternative approach.
</laravel-boost-guidelines>
```

### 2. MCP Server Configuration

**File**: `.mcp.json` (project root)

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": [
                "artisan",
                "boost:mcp"
            ]
        }
    }
}
```

### 3. Boost Configuration

**File**: `boost.json` (project root)

```json
{
    "agents": [
        "claude_code",
        "cursor"
    ],
    "editors": [
        "claude_code",
        "cursor"
    ],
    "guidelines": []
}
```

---

## Composer Scripts

Add to `composer.json` under `"scripts"`:

```json
{
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "analyse": [
            "./vendor/bin/phpstan analyse --memory-limit=2G"
        ],
        "check": [
            "@php artisan config:clear --ansi",
            "./vendor/bin/pint --test",
            "./vendor/bin/phpstan analyse --memory-limit=2G",
            "@php artisan test"
        ]
    }
}
```

---

## Pre-Commit Hook

**File**: `scripts/pre-commit-hook.sh`

Copy the pre-commit hook from this project's `scripts/pre-commit-hook.sh` file. It includes:
- Laravel Pint (auto-fix)
- PHP syntax validation
- Blade syntax validation
- PHPStan (staged files only)
- And more checks

**Installation**:
```bash
cp scripts/pre-commit-hook.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

---

## CI/CD Configuration

**File**: `.github/workflows/ci.yml`

Create GitHub Actions workflow:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  php:
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: "npm"

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"
          coverage: none
          tools: composer:v2

      - name: Install npm dependencies
        run: npm ci

      - name: Build assets (Vite)
        run: npm run build

      - name: Install Composer dependencies
        run: composer install --no-interaction --no-progress --prefer-dist

      - name: Pint (style)
        run: ./vendor/bin/pint --test

      - name: PHPStan
        run: ./vendor/bin/phpstan analyse --memory-limit=2G

      - name: PHPUnit
        env:
          APP_KEY: base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
          APP_ENV: testing
          DB_CONNECTION: sqlite
          DB_DATABASE: ":memory:"
          CACHE_STORE: array
          QUEUE_CONNECTION: sync
          SESSION_DRIVER: array
          MAIL_MAILER: array
        run: php artisan test --stop-on-failure

      - name: Upload Laravel logs on failure
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: laravel-logs
          path: storage/logs/laravel.log

      - name: Composer audit
        run: |
          # composer audit exits with:
          # - 0: no vulnerabilities / no abandoned packages
          # - 1: vulnerabilities found
          # - 2: abandoned packages found (no security advisories)
          # We fail CI on vulnerabilities, but do not fail on abandoned packages.
          composer audit --no-interaction || [ $? -eq 2 ]

  node:
    runs-on: ubuntu-latest
    timeout-minutes: 10
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: "npm"

      - name: Install npm dependencies
        run: npm ci

      - name: npm audit (high+)
        run: npm audit --audit-level=high
```

---

## Bootstrap Configuration (Laravel 12)

**File**: `bootstrap/app.php`

Example structure (customize as needed):

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases here
        // Example: $middleware->alias(['api-key' => \App\Http\Middleware\AuthenticateApiKey::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception handling configuration
    })->create();
```

---

## Layout Configuration

### Font Setup

Add to your main layout file (e.g., `resources/views/layouts/app.blade.php`):

```html
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
```

---

## Key Preferences & Rules

### Livewire Preference
- **Default**: Use Livewire for all interactive components
- **Exception**: Only use other frameworks (Inertia, Vue, React) if explicit permission is sought
- **Rationale**: Less JavaScript, simpler form handling, real-time updates

### Code Style
- Always use curly braces for control structures
- PHP 8 constructor property promotion
- Explicit return types required
- PHPDoc blocks preferred over comments
- TitleCase for Enum keys

### Testing
- PHPUnit only (convert any Pest tests)
- Use factories for test data
- Feature tests preferred over unit tests

### Database
- Prefer Eloquent relationships over raw queries
- Use `Model::query()` instead of `DB::`
- Eager load to prevent N+1 queries

---

## Setup Checklist

- [ ] Install Laravel 12 project
- [ ] Install Laravel Boost (`composer require laravel/boost --dev`)
- [ ] Install Laravel Breeze with Livewire stack
- [ ] Install NPM dependencies
- [ ] Copy Cursor rules (`.cursor/rules/laravel-boost.mdc`)
- [ ] Copy MCP config (`.mcp.json`)
- [ ] Copy Boost config (`boost.json`)
- [ ] Copy Pint config (`pint.json`)
- [ ] Copy PHPStan config (`phpstan.neon`)
- [ ] Copy Tailwind config (`tailwind.config.js`)
- [ ] Copy Vite config (`vite.config.js`)
- [ ] Copy PostCSS config (`postcss.config.js`)
- [ ] Copy pre-commit hook (`scripts/pre-commit-hook.sh`)
- [ ] Copy CI workflow (`.github/workflows/ci.yml`)
- [ ] Add composer scripts (analyse, check)
- [ ] Configure bootstrap/app.php for Laravel 12 structure
- [ ] Set up Figtree font in layout
- [ ] Test all tools (Pint, PHPStan, PHPUnit)

---

## Quick Commands Reference

```bash
# Development
composer dev                    # Run server, queue, logs, vite concurrently

# Code Quality
composer analyse                # Run PHPStan
composer check                  # Run all checks (Pint, PHPStan, Tests)
./vendor/bin/pint               # Fix code style
./vendor/bin/pint --test        # Check code style only

# Testing
php artisan test                # Run all tests
php artisan test --filter=name  # Run specific test

# Building
npm run dev                     # Development with HMR
npm run build                   # Production build
```

---

## Notes

- All configuration files are ready to copy/paste
- Cursor AI will automatically use the rules from `.cursor/rules/laravel-boost.mdc`
- MCP server (Laravel Boost) provides powerful Laravel-specific tools
- Pre-commit hook prevents committing code with errors
- CI/CD runs all checks automatically on push/PR



