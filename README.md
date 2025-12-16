<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Domain Monitor

A comprehensive domain monitoring and management platform built with Laravel 12, PostgreSQL, and Laravel Boost MCP.

## Features

- **Domain Management**: Track and manage domains with comprehensive metadata
- **Health Checks**: Automated HTTP, SSL, and DNS health checks
- **Platform Detection**: Automatic detection of website platforms (WordPress, Laravel, Next.js, etc.)
- **Hosting Detection**: Identify hosting providers and store admin links
- **Synergy Wholesale Integration**: Automated expiry date syncing for .com.au domains
- **DNS Management**: View and manage DNS records for .com.au domains
- **Brain Integration**: Event emission to Brain Nucleus for monitoring
- **Livewire UI**: Modern, responsive admin interface

## Requirements

- PHP 8.2+
- PostgreSQL 12+
- Composer
- Node.js & NPM
- Laravel 12

## Installation

1. Clone the repository:
```bash
git clone https://github.com/your-username/domain-monitor.git
cd domain-monitor
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Set up database:
```bash
# Update .env with your database credentials
php artisan migrate
php artisan db:seed --class=SuperadminSeeder
```

5. Build assets:
```bash
npm run build
```

6. Start the development server:
```bash
php artisan serve
```

## Environment Variables

See `DEPLOYMENT.md` for a complete list of required environment variables.

### Required
- `APP_NAME` - Application name
- `APP_ENV` - Environment (local/production)
- `APP_DEBUG` - Debug mode (false in production)
- `APP_URL` - Application URL
- `DB_*` - Database configuration
- `BRAIN_BASE_URL` - Brain API base URL
- `BRAIN_API_KEY` - Brain API key

### Optional
- `SYNERGY_WHOLESALE_*` - Synergy Wholesale API credentials (for .com.au domains)
- `DOMAIN_MONITOR_RECENT_FAILURES_HOURS` - Default “Recent failures” window (hours). Can be overridden in-app at `/settings/monitoring`.
- `DOMAIN_MONITOR_PRUNE_DOMAIN_CHECKS_DAYS` - Retain `domain_checks` history for N days (default 90).
- `DOMAIN_MONITOR_PRUNE_ELIGIBILITY_CHECKS_DAYS` - Retain `domain_eligibility_checks` history for N days (default 180).

## Scheduled Tasks

The application includes several scheduled tasks configured in `routes/console.php`:

- **Platform Detection**: Weekly on Sundays at 02:00 UTC
- **Hosting Detection**: Weekly on Sundays at 02:30 UTC
- **HTTP Health Checks**: Every hour
- **SSL Certificate Checks**: Daily at 03:00 UTC
- **DNS Checks**: Every 6 hours
- **Synergy Wholesale Sync**: Daily at 04:00 UTC
- **Prune Monitoring Data**: Daily at 09:00 UTC

Ensure Laravel's scheduler is running:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoring Settings UI

You can tune “recent” windows from the UI at:

- `/settings/monitoring`

The DB setting takes precedence; environment variables are used as defaults/fallbacks.

## Deployment

See `DEPLOYMENT.md` for detailed deployment instructions for Laravel Forge.

## Documentation

- `PROJECT-PLAN.md` - Project overview and completed steps
- `DEPLOYMENT.md` - Deployment guide for Laravel Forge
- `SYNERGY-SETUP.md` - Synergy Wholesale API setup
- `SYNERGY-API-FIELDS.md` - Available API fields and methods

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
