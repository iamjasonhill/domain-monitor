# Styling Quick Reference - Domain Monitor

Quick reference for duplicating the styling system.

## Installation Commands

```bash
# PHP dependencies
composer require laravel/breeze --dev
composer require livewire/livewire livewire/volt
php artisan breeze:install livewire

# NPM dependencies
npm install -D tailwindcss@^3.1.0 @tailwindcss/forms@^0.5.2 @tailwindcss/vite@^4.0.0 autoprefixer@^10.4.2 postcss@^8.4.31 vite@^7.0.7 laravel-vite-plugin@^2.0.0

# Build assets
npm run build
```

## Key Files to Copy

1. `tailwind.config.js` - Tailwind configuration with Figtree font
2. `postcss.config.js` - PostCSS configuration
3. `vite.config.js` - Vite configuration
4. `resources/css/app.css` - Tailwind directives
5. `resources/views/layouts/app.blade.php` - Main layout
6. `resources/views/livewire/layout/navigation.blade.php` - Navigation
7. `resources/views/components/*` - All Blade components

## Color Palette

| Purpose | Light Mode | Dark Mode |
|---------|-----------|-----------|
| Primary | `bg-blue-600` | `dark:bg-blue-500` |
| Success | `bg-green-500` | `dark:bg-green-900` |
| Warning | `bg-yellow-500` | `dark:bg-yellow-900` |
| Error | `bg-red-500` | `dark:bg-red-900` |
| Background | `bg-gray-100` | `bg-gray-900` |
| Card | `bg-white` | `bg-gray-800` |
| Text Primary | `text-gray-900` | `text-gray-100` |
| Text Secondary | `text-gray-500` | `text-gray-400` |

## Common Patterns

### Card Container
```blade
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
        {{ $slot }}
    </div>
</div>
```

### Primary Button
```blade
<button class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
    Button Text
</button>
```

### Status Badge (Success)
```blade
<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
    Active
</span>
```

### Table
```blade
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
```

### Flash Message (Alpine.js)
```blade
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="fixed top-4 right-4 z-50 max-w-sm w-full">
    <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg shadow-lg">
        {{ session('message') }}
    </div>
</div>
```

## Font

**Figtree** from Bunny Fonts:
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
```

## Tech Stack

- **Laravel Breeze** (Livewire stack)
- **Tailwind CSS v3**
- **Livewire v3**
- **Alpine.js** (included with Livewire)
- **Vite** for asset bundling
- **@tailwindcss/forms** plugin

## Development

```bash
npm run dev    # Development with HMR
npm run build  # Production build
```

