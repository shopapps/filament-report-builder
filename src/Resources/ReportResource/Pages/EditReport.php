<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Wjbecker\FilamentReportBuilder\Resources\ReportResource;
use Wjbecker\FilamentReportBuilder\Models\Report;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->url(fn (Report $record): string => route(static::getResource()::getRouteBaseName().'.view', $record)),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
