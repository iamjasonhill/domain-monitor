#!/bin/bash

# Laravel Pre-Commit Hook
# Comprehensive code quality checks before allowing commits
# Master version - combines best practices from all Laravel projects

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Running comprehensive pre-commit checks...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Get list of staged files
STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep -E '\.php$' || true)
STAGED_BLADE_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep -E '\.blade\.php$' || true)
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM || true)

HAS_ERRORS=false

# Check if there are any staged files to check
if [ -z "$STAGED_FILES" ]; then
    echo -e "${YELLOW}No files staged for commit. Skipping checks.${NC}"
    exit 0
fi

# 1. Laravel Pint Code Style Check (Auto-fix)
echo -e "${BLUE}1. Checking and fixing code style with Pint...${NC}"
if [ -n "$STAGED_PHP_FILES" ]; then
    if [ ! -f "./vendor/bin/pint" ]; then
        echo -e "  ${YELLOW}⚠️  Pint not found. Skipping code style check.${NC}"
        echo -e "  ${YELLOW}   Install with: composer require --dev laravel/pint${NC}"
    else
        PINT_HAD_ISSUES=false
        for FILE in $STAGED_PHP_FILES; do
            if [ -f "$FILE" ]; then
                if ./vendor/bin/pint --test "$FILE" > /tmp/pint-check-"$(basename "$FILE" | tr '/' '_')".txt 2>&1; then
                    echo -e "  ${GREEN}✅ $FILE${NC}"
                else
                    echo -e "  ${YELLOW}🔧 Fixing $FILE...${NC}"
                    ./vendor/bin/pint "$FILE" > /dev/null 2>&1
                    PINT_HAD_ISSUES=true
                fi
            fi
        done
        
        if [ "$PINT_HAD_ISSUES" = true ]; then
            echo -e "  ${GREEN}✅ Code style issues auto-fixed${NC}"
            echo -e "  ${YELLOW}Re-staging fixed files...${NC}"
            for FILE in $STAGED_PHP_FILES; do
                git add "$FILE" 2>/dev/null || true
            done
            echo -e "  ${GREEN}✅ Fixed files re-staged${NC}"
        else
            echo -e "  ${GREEN}✅ All files already formatted${NC}"
        fi
    fi
else
    echo -e "  ${YELLOW}⚠️  No PHP files staged${NC}"
fi
echo ""

# 2. PHP Syntax Check (quick validation)
echo -e "${BLUE}2. Validating PHP syntax...${NC}"
if [ -n "$STAGED_PHP_FILES" ]; then
    for FILE in $STAGED_PHP_FILES; do
        if [ -f "$FILE" ]; then
            if php -l "$FILE" > /dev/null 2>&1; then
                echo -e "  ${GREEN}✅ $FILE${NC}"
            else
                echo -e "  ${RED}❌ Syntax error in $FILE${NC}"
                php -l "$FILE"
                HAS_ERRORS=true
            fi
        fi
    done
else
    echo -e "  ${YELLOW}⚠️  No PHP files staged${NC}"
fi
echo ""

# 3. Blade Syntax & Security Check
echo -e "${BLUE}3. Validating Blade templates...${NC}"
if [ -n "$STAGED_BLADE_FILES" ]; then
    # Filter out files that might have been deleted but are still in the staged list
    ACTUAL_BLADE_FILES=""
    for FILE in $STAGED_BLADE_FILES; do
        if [ -f "$FILE" ]; then
            ACTUAL_BLADE_FILES="$ACTUAL_BLADE_FILES $FILE"
        fi
    done

    if [ -n "$ACTUAL_BLADE_FILES" ]; then
        if php artisan blade:validate $ACTUAL_BLADE_FILES > /tmp/blade-validate.txt 2>&1; then
            echo -e "  ${GREEN}✅ All staged Blade files passed validation${NC}"
        else
            echo -e "  ${RED}❌ Blade validation FAILED${NC}"
            cat /tmp/blade-validate.txt
            HAS_ERRORS=true
        fi
    else
        echo -e "  ${YELLOW}⚠️  No staged Blade files found on disk${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠️  No Blade files staged${NC}"
fi
echo ""

# 4. Missing Includes Check (Blade files)
echo -e "${BLUE}4. Checking for missing includes...${NC}"
if [ -n "$STAGED_BLADE_FILES" ]; then
    for FILE in $STAGED_BLADE_FILES; do
        if [ -f "$FILE" ]; then
            # Extract @include directives
            INCLUDES=$(grep -oP "@include\(['\"]([^'\"]+)['\"]\)" "$FILE" | sed "s/@include(['\"]//" | sed "s/['\"]).*//" || true)
            
            if [ -n "$INCLUDES" ]; then
                while IFS= read -r INCLUDE; do
                    # Convert dot notation to path (e.g., "admin.layout" -> "admin/layout.blade.php")
                    INCLUDE_PATH="resources/views/$(echo "$INCLUDE" | tr '.' '/').blade.php"
                    
                    if [ ! -f "$INCLUDE_PATH" ]; then
                        echo -e "  ${RED}❌ $FILE: Included file not found: $INCLUDE${NC}"
                        echo -e "     Expected: $INCLUDE_PATH${NC}"
                        HAS_ERRORS=true
                    fi
                done <<< "$INCLUDES"
            fi
        fi
    done
    
    if [ "$HAS_ERRORS" = false ]; then
        echo -e "  ${GREEN}✅ All includes found${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠️  No Blade files staged${NC}"
fi
echo ""

# 5. Codacy Compliance Check
echo -e "${BLUE}5. Checking Codacy compliance...${NC}"
if [ -n "$STAGED_PHP_FILES" ]; then
    if php artisan list 2>/dev/null | grep -q "check:codacy-compliance"; then
        CODACY_ERRORS=false
        for FILE in $STAGED_PHP_FILES; do
            if [ -f "$FILE" ]; then
                if php artisan check:codacy-compliance "$FILE" > /tmp/codacy-check-"$(basename "$FILE" | tr '/' '_')".txt 2>&1; then
                    echo -e "  ${GREEN}✅ $FILE${NC}"
                else
                    echo -e "  ${RED}❌ $FILE - Codacy issues found${NC}"
                    cat /tmp/codacy-check-"$(basename "$FILE" | tr '/' '_')".txt | head -10
                    CODACY_ERRORS=true
                    HAS_ERRORS=true
                fi
            fi
        done
        
        if [ "$CODACY_ERRORS" = false ]; then
            echo -e "  ${GREEN}✅ All files passed Codacy checks${NC}"
        fi
    else
        echo -e "  ${YELLOW}⚠️  Codacy compliance checker not found (optional)${NC}"
        echo -e "  ${YELLOW}   Create with: php artisan make:command CheckCodacyCompliance${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠️  No PHP files staged${NC}"
fi
echo ""

# 6. Blade Property Access Check
echo -e "${BLUE}6. Checking Blade property access...${NC}"
if [ -n "$STAGED_BLADE_FILES" ]; then
    if php artisan list 2>/dev/null | grep -q "check:blade-properties"; then
        # Check directories containing staged Blade files
        BLADE_DIRS=$(echo "$STAGED_BLADE_FILES" | xargs -I {} dirname {} | sort -u || true)
        
        for DIR in $BLADE_DIRS; do
            if php artisan check:blade-properties --path="$DIR" > /tmp/blade-props-check.txt 2>&1; then
                echo -e "  ${GREEN}✅ $DIR${NC}"
            else
                echo -e "  ${RED}❌ $DIR - Unsafe property access found${NC}"
                cat /tmp/blade-props-check.txt | head -10
                HAS_ERRORS=true
            fi
        done
    else
        echo -e "  ${YELLOW}⚠️  Blade property checker not found (optional)${NC}"
        echo -e "  ${YELLOW}   Create with: php artisan make:command CheckBladePropertyAccess${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠️  No Blade files staged${NC}"
fi
echo ""

# 7. Dark Mode Compliance Check (Blade files only)
echo -e "${BLUE}7. Checking dark mode compliance...${NC}"
if [ -n "$STAGED_BLADE_FILES" ]; then
    if [ -f "./check-dark-mode.sh" ]; then
        # Run dark mode check on staged Blade files
        BLADE_DIRS=$(echo "$STAGED_BLADE_FILES" | xargs -I {} dirname {} | sort -u | head -1 || true)
        if [ -n "$BLADE_DIRS" ]; then
            # Check the directory containing the staged Blade files
            if ./check-dark-mode.sh "$BLADE_DIRS" > /tmp/dark-mode-check.txt 2>&1; then
                echo -e "  ${GREEN}✅ Dark mode compliance check passed${NC}"
            else
                DARK_MODE_ISSUES=$(cat /tmp/dark-mode-check.txt | grep -E "❌|Found.*issues" || true)
                if [ -n "$DARK_MODE_ISSUES" ]; then
                    echo -e "  ${RED}❌ Dark mode issues found in staged Blade files${NC}"
                    cat /tmp/dark-mode-check.txt | grep -E "❌|Found.*issues" | head -10
                    echo -e "  ${YELLOW}Run './check-dark-mode.sh $BLADE_DIRS' for full details${NC}"
                    HAS_ERRORS=true
                else
                    echo -e "  ${GREEN}✅ Dark mode compliance check passed${NC}"
                fi
            fi
        fi
    else
        echo -e "  ${YELLOW}⚠️  Dark mode checker not found (optional)${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠️  No Blade files staged${NC}"
fi
echo ""

# 8. PHPStan Static Analysis (on staged files only)
echo -e "${BLUE}8. PHPStan check (staged files only)...${NC}"
if [ -n "$STAGED_PHP_FILES" ]; then
    if [ ! -f "./vendor/bin/phpstan" ]; then
        echo -e "  ${YELLOW}⚠️  PHPStan not found. Skipping static analysis.${NC}"
        echo -e "  ${YELLOW}   Install with: composer require --dev larastan/larastan${NC}"
    else
        # Create temporary PHPStan config that only analyzes staged files
        # We'll analyze the entire codebase but only report errors in staged files
        PHPSTAN_HAD_ERRORS=false
        
        # Run PHPStan and filter output to only show errors in staged files
        PHPSTAN_OUTPUT=$(./vendor/bin/phpstan analyse --memory-limit=2G --no-progress 2>&1 || true)
        
        # Filter output to only show errors in staged files
        STAGED_ERRORS=""
        for FILE in $STAGED_PHP_FILES; do
            FILE_ERRORS=$(echo "$PHPSTAN_OUTPUT" | grep "$FILE" || true)
            if [ -n "$FILE_ERRORS" ]; then
                STAGED_ERRORS="${STAGED_ERRORS}${FILE_ERRORS}\n"
                PHPSTAN_HAD_ERRORS=true
            fi
        done
        
        if [ "$PHPSTAN_HAD_ERRORS" = true ]; then
            echo -e "  ${RED}❌ PHPStan errors found in staged files:${NC}"
            echo -e "$STAGED_ERRORS" | while IFS= read -r LINE; do
                if [ -n "$LINE" ]; then
                    echo -e "  ${RED}   $LINE${NC}"
                fi
            done
            HAS_ERRORS=true
        else
            echo -e "  ${GREEN}✅ No PHPStan errors in staged files${NC}"
        fi
    fi
else
    echo -e "  ${YELLOW}⚠️  No PHP files staged${NC}"
fi
echo ""

# 9. CodeRabbit CLI Check (AI Code Review) - DISABLED
# CodeRabbit runs automatically on PRs, so pre-commit hook is disabled by default
# To enable: export ENABLE_CODERABBIT_PRE_COMMIT=true
echo -e "${BLUE}9. CodeRabbit check skipped (runs automatically on PRs)${NC}"
if [ "${ENABLE_CODERABBIT_PRE_COMMIT:-false}" = "true" ]; then
    if [ -n "$STAGED_FILES" ]; then
        if command -v coderabbit > /dev/null 2>&1; then
            echo -e "  ${BLUE}CodeRabbit pre-commit enabled via ENABLE_CODERABBIT_PRE_COMMIT=true${NC}"
            echo -e "  ${YELLOW}   Running CodeRabbit review...${NC}"
            # Run CodeRabbit in background (non-blocking)
            (
                coderabbit --prompt-only -t uncommitted > /tmp/coderabbit-review-$$.log 2>&1
            ) &
            echo -e "  ${GREEN}✅ CodeRabbit started (PID: $!)${NC}"
        else
            echo -e "  ${YELLOW}⚠️  CodeRabbit CLI not found${NC}"
        fi
    fi
else
    echo -e "  ${YELLOW}ℹ️  CodeRabbit reviews PRs automatically - no need for pre-commit hook${NC}"
    echo -e "  ${YELLOW}   To enable pre-commit: export ENABLE_CODERABBIT_PRE_COMMIT=true${NC}"
fi
echo ""

# Final result
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ "$HAS_ERRORS" = true ]; then
    echo -e "${RED}❌ Pre-commit checks FAILED${NC}"
    echo -e "${RED}Please fix the issues above before committing.${NC}"
    echo ""
    echo -e "${YELLOW}Helpful commands:${NC}"
    echo -e "  ${YELLOW}• Run './vendor/bin/pint' to fix code style issues${NC}"
    echo -e "  ${YELLOW}• Run 'php artisan check:codacy-compliance <file>' to see details${NC}"
    echo -e "  ${YELLOW}• Run 'php artisan check:blade-properties --path=<dir>' to see details${NC}"
    echo -e "  ${YELLOW}• Run './check-dark-mode.sh <directory>' to check dark mode compliance${NC}"
    echo -e "  ${YELLOW}• Run './vendor/bin/phpstan analyse --memory-limit=2G' to check PHPStan errors${NC}"
    echo ""
    exit 1
else
    echo -e "${GREEN}✅ All pre-commit checks passed!${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    exit 0
fi

