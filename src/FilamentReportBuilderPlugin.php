<?php

namespace Wjbecker\FilamentReportBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentReportBuilderPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-report-builder';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            Resources\ReportResource::class
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
