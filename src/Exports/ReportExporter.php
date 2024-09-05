<?php

namespace Wjbecker\FilamentReportBuilder\Exports;

use Wjbecker\FilamentReportBuilder\Models\Report;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ReportExporter extends Exporter
{
    public static function getColumns(): array
    {
        $id = Str::of(url()->previous())->between('reports/', '/view')->toInteger();
        $report = Report::find($id);

        $columns = [];
        foreach ($report->data['columns'] as $header) {
            $data = json_decode($header['column_data']);
            $columns[] = ExportColumn::make($data->item)
                ->label($header['column_title'])
                ->formatStateUsing(function ($state): string {
                    if ($state && gettype($state) === 'object' && class_exists(get_class($state))) {
                        $class = new \ReflectionClass(get_class($state));

                        if ($class->isSubclassOf(Carbon::class)) {
                            return $state->toDateString();
                        }

                        if ($class->isEnum()) {
                            return $state->getLabel();
                        }

                        return '';
                    } elseif(is_null($state)) {
                        return '';
                    }

                    return $state;
                });
        }

        return $columns;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your report export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
