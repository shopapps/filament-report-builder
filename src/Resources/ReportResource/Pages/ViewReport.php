<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Wjbecker\FilamentReportBuilder\Enums\ReportConditionsEnum;
use Wjbecker\FilamentReportBuilder\Enums\ReportSpecialDateEnum;
use Wjbecker\FilamentReportBuilder\Exports\ReportExporter;
use Wjbecker\FilamentReportBuilder\Resources\ReportResource;
use Wjbecker\FilamentReportBuilder\Models\Report;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ViewReport extends Page implements HasTable
{
    use InteractsWithRecord, InteractsWithTable;

    protected static string $resource = ReportResource::class;

    protected static string $view = 'filament-report-builder::report-resource.pages.view-report';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name.' Report';
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()->label('Export')
                ->exporter(ReportExporter::class)
                ->columnMapping(false)
                ->fileName(function() {
                    if (isset($this->getRecord()->data['filename']) && $this->getRecord()->data['filename'] != '') {
                        return $this->getRecord()->data['filename'];
                    }
                    return Str::of($this->getRecord()->name)->snake().'_'.now()->toDateString();
                })
                ->keyBindings('mod+x'),
            Action::make('edit')
                ->url(fn (Report $record): string => route(static::getResource()::getRouteBaseName().'.edit', $record))
                ->keyBindings('mod+e'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $report = $this->getRecord();

                /** @var Builder $query */
                $query = (new $report->data['source'])::query();
                if (isset($report->data['with'])) {
                    $query->with($report->data['with']);
                }

                if (isset($report->data['filter_groups'])) {
                    foreach($report->data['filter_groups'] as $group) {
                        $query->where(function ($query) use ($group) {
                            foreach($group['filters'] as $filter) {
                                $attribute = json_decode($filter['attribute']);
                                $query->when(isset($attribute->type), function (Builder $query) use ($filter, $attribute) {
                                    $query->orWhereRelation($attribute->name, function (Builder $query) use ($filter) {
                                        return $this->filterQuery($query, $filter);
                                    });
                                }, function (Builder $query) use ($filter) {
                                    return $this->filterQuery($query, $filter);
                                });
                            }
                        });
                    }
                }


                if (isset($report->data['with_trashed']) && $report->data['with_trashed']) {
                    $query->withTrashed();
                }

//                dd($query->toRawSql());

                return $query;
            })
            ->columns($this->getColumns());
    }

    private function filterQuery(Builder $query, $filter): Builder
    {
        $attribute = json_decode($filter['attribute']);
        $condition = ReportConditionsEnum::from($filter['condition']);

        $value = match ($condition) {
            ReportConditionsEnum::BEGINS_WITH, ReportConditionsEnum::NOT_BEGINS_WITH => $filter['value'].'%',
            ReportConditionsEnum::ENDS_WITH, ReportConditionsEnum::NOT_ENDS_WITH => '%'.$filter['value'],
            ReportConditionsEnum::CONTAINS, ReportConditionsEnum::NOT_CONTAINS => '%'.$filter['value'].'%',
            ReportConditionsEnum::IS_NOT_NULL, ReportConditionsEnum::IS_NULL => null,
            default => $filter['value'],
        };

//        dd($attribute, $condition, $filter, $value);

        if ($condition === ReportConditionsEnum::IS_NULL) {
            return $query->whereNull($attribute->item);
        }

        if ($condition === ReportConditionsEnum::IS_NOT_NULL) {
            return $query->whereNotNull($attribute->item);
        }

        if ($attribute->cast === null) {
            return $query->where($attribute->item, $condition->getOperator(), $value);
        }

        if ($attribute->cast === 'date' || $attribute->cast === 'datetime') {
            if ($condition === ReportConditionsEnum::SPECIAL_DATE) {
                $reportSpecialDate = ReportSpecialDateEnum::from($filter['special']);
                $values = $reportSpecialDate->getCarbonDates();
                $condition = ReportConditionsEnum::from($reportSpecialDate->getCondition());

                if (is_array($values)) {
                    list($value, $value2) = $values;
                } else {
                    $value = $values;
                }
            } else {
                $value = Carbon::make($value);
                if (isset($filter['value2'])) {
                    $value2 = Carbon::make($filter['value2']);
                }
            }

            if ($attribute->cast === 'datetime') {
                $value = $value->startOfDay();
                $value2 = isset($filter['value2']) ? $value2->endOfDay() : null;
            }

            if ($condition === ReportConditionsEnum::BETWEEN) {
                return $query->whereBetween($attribute->item, [$value, $value2]);
            }

            if ($condition === ReportConditionsEnum::NOT_BETWEEN) {
                return $query->whereNotBetween($attribute->item, [$value, $value2]);
            }

            return $query->whereDate($attribute->item, $condition->getOperator(), $value ?? null);
        }

        if (is_string($attribute->cast) && enum_exists($enum = $attribute->cast)) {
            return $query->where($attribute->item, $condition->getOperator(), $enum::from($value));
        }

        return $query->where($attribute->item, $condition->getOperator(), $value);
    }

    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->getRecord()->data['columns'] as $header) {
            $attribute = json_decode($header['column_data']);
            $columns[] = TextColumn::make((isset($attribute->name) ? $attribute->name.'.' : '').$attribute->item)
                ->label($header['column_title'])
                ->sortable(in_array('is_sortable', $header['column_options']))
                ->searchable(in_array('is_searchable', $header['column_options']));
        }

        return $columns;
    }
}
