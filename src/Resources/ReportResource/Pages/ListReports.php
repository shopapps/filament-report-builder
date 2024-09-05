<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Wjbecker\FilamentReportBuilder\Resources\ReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Report'),
        ];
    }
}
