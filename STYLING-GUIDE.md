# Domain Monitor Styling Guide

This document provides detailed instructions on how to duplicate the styling system from Domain Monitor to your Website Operations system.

## Overview

The Domain Monitor project uses:
- **Laravel Breeze** (with Livewire stack) for authentication and base UI
- **Tailwind CSS v3** for utility-first styling
- **Livewire v3** for interactive components
- **Alpine.js** (included with Livewire) for lightweight JavaScript interactions
- **Vite** for asset bundling
- **Figtree font** from Bunny Fonts for typography

---

## Step 1: Install Dependencies

### PHP Dependencies (Composer)

```bash
composer require laravel/breeze --dev
composer require livewire/livewire
composer require livewire/volt
```

### Install Breeze with Livewire Stack

```bash
php artisan breeze:install livewire
```

This will:
- Install Livewire components
- Set up authentication views
- Create base layouts
- Configure Tailwind CSS

### NPM Dependencies

```bash
npm install
```

Or manually install:

```bash
npm install -D tailwindcss@^3.1.0 @tailwindcss/forms@^0.5.2 @tailwindcss/vite@^4.0.0 autoprefixer@^10.4.2 postcss@^8.4.31 vite@^7.0.7 laravel-vite-plugin@^2.0.0
```

---

## Step 2: Configuration Files

### 1. `tailwind.config.js`

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

**Key Points:**
- Uses `@tailwindcss/forms` plugin for styled form inputs
- Custom font: **Figtree** (from Bunny Fonts)
- Content paths include all Blade templates

### 2. `postcss.config.js`

```javascript
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

### 3. `vite.config.js`

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

### 4. `resources/css/app.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**Note:** This is the minimal setup. All styling is done via Tailwind utility classes.

### 5. `resources/js/app.js`

```javascript
import './bootstrap';
```

### 6. `resources/js/bootstrap.js`

```javascript
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

---

## Step 3: Layout Structure

### Main Layout: `resources/views/layouts/app.blade.php`

Key features:
- Dark mode support (`dark:` variants)
- Flash message notifications (success/error)
- Alpine.js for interactive elements
- Livewire navigation component

**Structure:**
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <!-- Fonts from Bunny Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        
        <!-- Vite assets -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <livewire:layout.navigation />
            
            <!-- Page Heading (optional) -->
            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif
            
            <!-- Flash Messages with Alpine.js -->
            <!-- Success messages -->
            <!-- Error messages -->
            
            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
```

---

## Step 4: Color Scheme & Design Patterns

### Color Palette

**Primary Colors:**
- Blue: `bg-blue-500`, `text-blue-600`, `hover:bg-blue-700` (primary actions, links)
- Green: `bg-green-500`, `text-green-600` (success, active states)
- Yellow: `bg-yellow-500` (warnings, expiring items)
- Red: `bg-red-500`, `text-red-600` (errors, failures)
- Orange: `bg-orange-500` (warnings, eligibility issues)

**Neutral Colors:**
- Background: `bg-gray-100` (light), `bg-gray-900` (dark mode)
- Cards: `bg-white` (light), `bg-gray-800` (dark mode)
- Text: `text-gray-900` (light), `text-gray-100` (dark mode)
- Borders: `border-gray-200` (light), `border-gray-700` (dark mode)

### Common Patterns

#### 1. **Card/Container**
```blade
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
        <!-- Content -->
    </div>
</div>
```

#### 2. **Stats Cards**
```blade
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow">
    <div class="p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                <!-- Icon -->
            </div>
            <div class="ml-5 w-0 flex-1">
                <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Label</dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Value</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
```

#### 3. **Primary Button**
```blade
<button class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
    Button Text
</button>
```

#### 4. **Status Badges**
```blade
<!-- Success/Active -->
<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
    Active
</span>

<!-- Warning -->
<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
    Warning
</span>

<!-- Error/Inactive -->
<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
    Failed
</span>
```

#### 5. **Tables**
```blade
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Header
                </th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    Content
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

#### 6. **Flash Messages (Alpine.js)**
```blade
<!-- Success -->
<div x-data="{ show: true }" 
     x-show="show" 
     x-init="setTimeout(() => show = false, 5000)" 
     class="fixed top-4 right-4 z-50 max-w-sm w-full">
    <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg shadow-lg">
        <div class="flex items-center justify-between">
            <span>{{ session('message') }}</span>
            <button @click="show = false" class="text-green-700 dark:text-green-300 hover:text-green-900">
                <!-- Close icon -->
            </button>
        </div>
    </div>
</div>
```

---

## Step 5: Navigation Component

The navigation uses Livewire Volt component with Alpine.js for mobile menu.

**Key Features:**
- Responsive design (mobile hamburger menu)
- Active route highlighting
- Dark mode support
- User dropdown menu

**Navigation Link Component:** `resources/views/components/nav-link.blade.php`

```blade
@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 dark:border-indigo-600 text-sm font-medium leading-5 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:outline-none focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
```

---

## Step 6: Component Library

### Available Blade Components (from Breeze)

Located in `resources/views/components/`:

1. **`primary-button.blade.php`** - Primary action button
2. **`secondary-button.blade.php`** - Secondary button
3. **`danger-button.blade.php`** - Destructive actions
4. **`nav-link.blade.php`** - Navigation links with active state
5. **`dropdown.blade.php`** - Dropdown menu component
6. **`modal.blade.php`** - Modal dialog
7. **`text-input.blade.php`** - Form text input
8. **`input-label.blade.php`** - Form label
9. **`input-error.blade.php`** - Validation error display

---

## Step 7: Dark Mode

Dark mode is built into Tailwind CSS and works automatically with the `dark:` prefix.

**Implementation:**
- All components include dark mode variants
- Uses system preference or manual toggle (if implemented)
- Pattern: `bg-white dark:bg-gray-800`
- Text: `text-gray-900 dark:text-gray-100`

**Example:**
```blade
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
    Content
</div>
```

---

## Step 8: Typography

**Font:** Figtree (400, 500, 600 weights)

**Font Loading:**
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
```

**Usage:**
- Applied via `font-sans` class (configured in Tailwind config)
- Default font family for entire application

**Text Sizes:**
- `text-xs` - Extra small (12px)
- `text-sm` - Small (14px)
- `text-base` - Base (16px)
- `text-lg` - Large (18px)
- `text-xl` - Extra large (20px)
- `text-2xl` - 2X large (24px)

**Font Weights:**
- `font-normal` - 400
- `font-medium` - 500
- `font-semibold` - 600
- `font-bold` - 700

---

## Step 9: Spacing & Layout

### Container Widths
- `max-w-7xl` - Maximum content width (1280px)
- `max-w-6xl` - Large container (1152px)
- `max-w-4xl` - Medium container (896px)

### Padding
- Cards: `p-6` (24px)
- Sections: `px-4 sm:px-6 lg:px-8` (responsive)
- Buttons: `px-4 py-2`

### Margins
- Between sections: `mb-8` (32px)
- Grid gaps: `gap-6` (24px)

### Grid System
```blade
<!-- Responsive grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Items -->
</div>
```

---

## Step 10: Build & Development

### Development Mode
```bash
npm run dev
```

This runs Vite in watch mode for hot module replacement.

### Production Build
```bash
npm run build
```

Compiles and minifies assets for production.

### Combined Dev Command (if using Laravel Boost)
```bash
composer dev
```

Runs server, queue, logs, and Vite concurrently.

---

## Step 11: Key Styling Principles

1. **Utility-First:** Use Tailwind utility classes directly in templates
2. **No Custom CSS:** Minimal custom CSS, rely on Tailwind
3. **Dark Mode:** Always include dark mode variants
4. **Responsive:** Use responsive prefixes (`sm:`, `md:`, `lg:`, `xl:`)
5. **Consistent Spacing:** Use Tailwind spacing scale (4px increments)
6. **Accessibility:** Include focus states, proper contrast
7. **Transitions:** Add smooth transitions for interactive elements

---

## Step 12: Common Utility Classes Reference

### Layout
- `flex` - Flexbox container
- `grid` - CSS Grid
- `hidden` / `block` - Visibility
- `overflow-x-auto` - Horizontal scroll

### Spacing
- `p-{size}` - Padding
- `m-{size}` - Margin
- `space-x-{size}` - Horizontal spacing between children
- `gap-{size}` - Grid/Flex gap

### Colors
- `bg-{color}-{shade}` - Background
- `text-{color}-{shade}` - Text color
- `border-{color}-{shade}` - Border color

### Typography
- `font-{weight}` - Font weight
- `text-{size}` - Font size
- `uppercase` / `lowercase` - Text transform
- `tracking-widest` - Letter spacing

### Effects
- `shadow-sm` / `shadow-md` / `shadow-lg` - Box shadow
- `rounded-md` / `rounded-lg` - Border radius
- `transition` - CSS transitions
- `hover:`, `focus:`, `active:` - State variants

---

## Step 13: Integration Checklist

- [ ] Install Laravel Breeze with Livewire stack
- [ ] Install NPM dependencies
- [ ] Copy `tailwind.config.js` configuration
- [ ] Copy `postcss.config.js` configuration
- [ ] Copy `vite.config.js` configuration
- [ ] Set up `resources/css/app.css` with Tailwind directives
- [ ] Set up `resources/js/app.js` and `bootstrap.js`
- [ ] Copy main layout file (`layouts/app.blade.php`)
- [ ] Copy navigation component
- [ ] Copy Blade components from `resources/views/components/`
- [ ] Add Figtree font link to layout
- [ ] Test dark mode variants
- [ ] Build assets (`npm run build`)
- [ ] Test responsive design
- [ ] Verify all Tailwind classes are working

---

## Additional Resources

- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Laravel Breeze Documentation](https://laravel.com/docs/breeze)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Alpine.js Documentation](https://alpinejs.dev/)

---

## Notes

- All styling is done via Tailwind utility classes - no custom CSS files needed
- Dark mode is built-in and works automatically
- The `@tailwindcss/forms` plugin styles all form inputs automatically
- Alpine.js is included with Livewire for lightweight JavaScript interactions
- Vite provides hot module replacement during development

