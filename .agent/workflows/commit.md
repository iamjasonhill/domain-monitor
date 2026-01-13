---
description: Validate and commit code changes with all quality checks
---

# Commit Workflow

Use this workflow before committing any code changes. This ensures validation happens BEFORE the pre-commit hook, catching errors earlier.

## Steps

// turbo
1. Identify staged/modified Blade files and run validation:
```bash
php artisan blade:validate <blade-files>
```

// turbo
2. Identify staged/modified PHP files and run PHPStan:
```bash
./vendor/bin/phpstan analyse <php-files> --memory-limit=2G
```

// turbo
3. Run the relevant tests:
```bash
php artisan test
```

4. If all checks pass, stage and commit:
```bash
git add . && git commit -m "<commit-message>"
```

5. Push and deploy:
```bash
git push && ssh forge@170.64.246.28 "cd /home/forge/domains.again.com.au/current && git pull && php artisan migrate --force"
```

## Quick Reference

| File Type | Validation Command |
|-----------|-------------------|
| `.blade.php` | `php artisan blade:validate <file>` |
| `.php` | `./vendor/bin/phpstan analyse <file> --memory-limit=2G` |
| Any | `php artisan test` |
