<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Wjbecker\FilamentReportBuilder\Resources\ReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReport extends CreateRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $with = collect($data['data']['columns'])
            ->map(fn ($item) => json_decode($item['column_data'], true))
            ->filter(fn ($item) => isset($item['name']))
            ->map(fn ($item) => $item['name'])->unique()->flatten(1);

        if ($with->isNotEmpty()) {
            $data['data']['with'] = $with->toArray();
        }

        return $data;
    }
}
