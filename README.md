# Filament Report Builder

### Dynamic Report Builder using Laravel Eloquent Models

---

## Installation

Install package via composer:
```bash
composer require wjbecker/filament-report-builder
```

Publish & migrate migration files
```bash
php artisan vendor:publish --tag=filament-report-builder-migrations
php artisan migrate
```

Filament Export actions
```bash
# Laravel 11 and higher
php artisan make:queue-batches-table
php artisan make:notifications-table
 
# Laravel 10
php artisan queue:batches-table
php artisan notifications:table

php artisan vendor:publish --tag=filament-actions-migrations
php artisan migrate
```
---

## Panel Configuration

Include this plugin in your panel configuration and enable database notifications:

```php
use Wjbecker\FilamentReportBuilder\FilamentReportBuilderPlugin;

return $panel
    // ...
    ->databaseNotifications()
    ->plugins([
        // ... Other Plugins
        FilamentReportBuilderPlugin::make()
    ])
```
