# Frontend & Asset Management

## Overview

The Base Tenant package comes with pre-compiled Tailwind CSS and JavaScript assets. The parent application can extend or override these styles while maintaining the package's core functionality.

## Default Assets

The package includes:
- `public/css/app.css` - Compiled Tailwind CSS
- `public/js/app.js` - JavaScript bundle
- Tailwind configuration with form plugin

## Using Package Assets

### In Your Blade Layouts

```blade
<!DOCTYPE html>
<html>
<head>
    <!-- Package CSS -->
    <link rel="stylesheet" href="{{ asset('vendor/base-tenant/css/app.css') }}">

    <!-- Your custom overrides (optional) -->
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body>
    <!-- Your content -->

    <!-- Package JS -->
    <script src="{{ asset('vendor/base-tenant/js/app.js') }}"></script>

    <!-- Your custom JS (optional) -->
    <script src="{{ mix('js/app.js') }}"></script>
</body>
</html>
```

## Extending Tailwind Configuration

### Option 1: Extend Package Tailwind Config

In your application's `tailwind.config.js`:

```javascript
import packageConfig from './vendor/base/tenant/tailwind.config.js';

export default {
    presets: [packageConfig],
    content: [
        ...packageConfig.content,
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            // Your custom theme extensions
            colors: {
                primary: {
                    50: '#f0f9ff',
                    // ... your colors
                },
            },
        },
    },
};
```

### Option 2: Override Styles with CSS

Create `resources/css/vendor/base-tenant/overrides.css`:

```css
/* Override package styles */
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700;
}

/* Add your custom styles */
.custom-component {
    /* Your styles */
}
```

Then import it in your main CSS file:

```css
@import './vendor/base-tenant/overrides.css';
```

## Building Assets

### For Development

```bash
cd vendor/base/tenant
npm install
npm run dev
```

### For Production

```bash
cd vendor/base/tenant
npm install
npm run build
```

The compiled assets will be placed in the `public` directory and automatically published to your application's `public/vendor/base-tenant` directory.

## Publishing Assets

After installing the package, publish the assets:

```bash
php artisan vendor:publish --tag=base-tenant-assets --force
```

Use `--force` when you want to re-publish after package updates.

## Custom Vite Integration

If you want to compile everything together in your application:

```javascript
// vite.config.js in your application
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'vendor/base/tenant/resources/css/app.css',
                'vendor/base/tenant/resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
```

## Component Styling

All views use Tailwind utility classes. You can:

1. **Publish views** to customize markup and classes:
   ```bash
   php artisan vendor:publish --tag=base-tenant-views
   ```

2. **Extend via configuration** - Some components accept custom classes:
   ```php
   // config/base-tenant.php
   'ui' => [
       'button_classes' => 'px-4 py-2 rounded-lg',
       'input_classes' => 'border-gray-300 focus:border-blue-500',
   ],
   ```

## Dark Mode Support

The package includes dark mode classes. To enable dark mode:

```javascript
// tailwind.config.js
export default {
    darkMode: 'class', // or 'media'
    // ... rest of config
};
```

Then add dark mode classes to your HTML:

```html
<html class="dark">
```

## Best Practices

1. **Don't modify package files directly** - Always extend or override
2. **Publish assets after updates** - Run `vendor:publish --tag=base-tenant-assets --force`
3. **Use CSS layers** - Organize your custom styles with `@layer`
4. **Leverage Tailwind's JIT** - The package uses JIT mode for optimal performance
5. **Cache busting** - Use Laravel Mix or Vite versioning for production

## Troubleshooting

### Styles not applying

1. Clear Laravel caches: `php artisan optimize:clear`
2. Re-publish assets: `php artisan vendor:publish --tag=base-tenant-assets --force`
3. Clear browser cache

### Conflicting styles

1. Use more specific selectors
2. Use `!important` sparingly
3. Leverage Tailwind's `@layer` directive

### Build errors

1. Ensure Node.js version is compatible (18+)
2. Delete `node_modules` and reinstall
3. Check for conflicting Tailwind versions
