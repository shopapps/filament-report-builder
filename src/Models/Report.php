<?php

namespace Wjbecker\FilamentReportBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'is_built_in',
        'category',
        'description',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
