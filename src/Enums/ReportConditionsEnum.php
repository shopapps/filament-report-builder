<?php

namespace Wjbecker\FilamentReportBuilder\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportConditionsEnum: string implements HasLabel
{
    case EQUAL = 'equal';
    case NOT_EQUAL = 'not_equal';
    case BEGINS_WITH = 'begins_with';
    case NOT_BEGINS_WITH = 'not_begins_with';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case ENDS_WITH = 'ends_with';
    case NOT_ENDS_WITH = 'not_ends_with';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
    case GREATER = 'greater';
    case GREATER_OR_EQUAL = 'greater_or_equal';
    case LESS = 'less';
    case LESS_OR_EQUAL = 'less_or_equal';
    case BETWEEN = 'between';
    case NOT_BETWEEN = 'not_between';
    case SPECIAL_DATE = 'special_date';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EQUAL => 'Equal',
            self::NOT_EQUAL => 'Not Equal',
            self::BEGINS_WITH => 'Begins With',
            self::NOT_BEGINS_WITH => 'Doesn\'t Begin With',
            self::CONTAINS => 'Contains',
            self::NOT_CONTAINS => 'Doesn\'t Contain',
            self::ENDS_WITH => 'Ends With',
            self::NOT_ENDS_WITH => 'Doesn\'t End With',
            self::IS_EMPTY => 'Is Empty',
            self::IS_NOT_EMPTY => 'Is Not Empty',
            self::IS_NULL => 'Is Null',
            self::IS_NOT_NULL => 'Is Not Null',
            self::GREATER => 'Greater',
            self::GREATER_OR_EQUAL => 'Greater Or Equal',
            self::LESS => 'Less',
            self::LESS_OR_EQUAL => 'Less Or Equal',
            self::BETWEEN => 'Between',
            self::NOT_BETWEEN => 'Not Between',
            self::SPECIAL_DATE => 'Special Date',
        };
    }

    public function getOperator(): ?string
    {
        return match ($this) {
            self::EQUAL, self::IS_EMPTY => '=',
            self::NOT_EQUAL, self::IS_NOT_EMPTY => '!=',
            self::BEGINS_WITH, self::CONTAINS, self::ENDS_WITH => 'like',
            self::NOT_BEGINS_WITH, self::NOT_CONTAINS, self::NOT_ENDS_WITH => 'not like',
            self::GREATER => '>',
            self::GREATER_OR_EQUAL => '>=',
            self::LESS => '<',
            self::LESS_OR_EQUAL => '<=',
            default => ''
        };
    }
}
