# Pre-Deployment Checks

This guide shows you how to check for errors before deploying to catch issues like PHPStan errors, code style issues, and test failures.

## Quick Commands

### Run All Checks
```bash
composer check
```

This runs:
1. Clear config cache
2. Pint (code style check)
3. PHPStan (static analysis)
4. PHPUnit (tests)

### Individual Checks

#### PHPStan (Static Analysis)
```bash
composer analyse
# or
./vendor/bin/phpstan analyse --memory-limit=2G
```

**What it checks:**
- Undefined methods/properties
- Type mismatches
- Missing return types
- Unused variables
- And more...

#### Code Style (Pint)
```bash
./vendor/bin/pint --test
```

**What it checks:**
- PSR-12 code style compliance
- Formatting issues

#### Tests
```bash
php artisan test
```

**What it checks:**
- All PHPUnit tests pass

## Automated Checks

### GitHub Actions CI

Your CI pipeline (`.github/workflows/ci.yml`) automatically runs on:
- Every push to `main`
- Every pull request

**Checks performed:**
1. ✅ Pint (code style)
2. ✅ PHPStan (static analysis)
3. ✅ PHPUnit (tests)
4. ✅ Composer audit (security vulnerabilities)
5. ✅ npm audit (npm security)

**If any check fails, the CI will fail and prevent merging.**

### Pre-Commit Hooks

Your project has pre-commit hooks that run automatically when you commit:
- Pint (auto-fixes code style)
- PHP syntax validation
- Blade syntax validation
- And more...

## Recommended Workflow

### Before Committing
```bash
# Quick check before committing
composer analyse
```

### Before Pushing
```bash
# Full check before pushing
composer check
```

### Before Deploying
```bash
# Full check + build assets
composer check
npm run build
```

## Common Issues & Solutions

### PHPStan Errors

**Error:** `Call to an undefined method`
**Solution:** Check if the method exists on the correct object. Use `$this->resource->method()` for model methods in resources.

**Error:** `Access to an undefined property`
**Solution:** Add the property to the PHPDoc `@property` annotations.

### Fixing PHPStan Errors

1. Run PHPStan to see errors:
   ```bash
   composer analyse
   ```

2. Fix the errors in your code

3. Re-run to verify:
   ```bash
   composer analyse
   ```

4. Commit when all checks pass

## CI/CD Integration

### GitHub Actions

Your CI already runs PHPStan. If it fails:
1. Check the "Actions" tab in GitHub
2. View the failed workflow
3. See the PHPStan output
4. Fix errors locally
5. Push again

### Local CI Simulation

To simulate what CI does locally:
```bash
# Install dependencies
composer install --no-interaction --no-progress --prefer-dist
npm ci

# Run checks (same as CI)
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=2G
php artisan test
```

## Tips

1. **Run PHPStan frequently** - Catch errors early
2. **Fix errors immediately** - Don't let them accumulate
3. **Use IDE integration** - Many IDEs can show PHPStan errors inline
4. **Check CI before merging** - Always wait for green CI status
5. **Run full check before deploying** - Use `composer check` before production deploys

## IDE Integration

### PHPStorm / IntelliJ
- PHPStan can be integrated as an external tool
- Shows errors inline in the editor

### VS Code
- Install PHPStan extension
- Shows errors in Problems panel

## Summary

✅ **Before commit:** `composer analyse`  
✅ **Before push:** `composer check`  
✅ **Before deploy:** `composer check && npm run build`  
✅ **CI runs automatically** on push/PR  
✅ **Pre-commit hooks** catch issues early

