<?php

namespace Wjbecker\FilamentReportBuilder\Support;

use Wjbecker\FilamentReportBuilder\Traits\HasReporting;
use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\DecimalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use UnitEnum;

class DatabaseSchema
{
    protected static array $relationMethods = [
        'hasOneThrough',
        'hasOne',
        'belongsTo',
        'morphOne',
        'morphTo',
    ];

    protected static array $typeMappings = [
        'bit' => 'string',
        'citext' => 'string',
        'enum' => 'string',
        'geometry' => 'string',
        'geomcollection' => 'string',
        'linestring' => 'string',
        'ltree' => 'string',
        'multilinestring' => 'string',
        'multipoint' => 'string',
        'multipolygon' => 'string',
        'point' => 'string',
        'polygon' => 'string',
        'sysname' => 'string',
    ];

    public static function getModels()
    {
        return collect(File::allFiles(app_path().'/Models'))
            ->mapWithKeys(function ($item) {
                $path = $item->getRelativePathName();
                $model = strtr(substr($path, 0, strrpos($path, '.')), '/', '\\');
                return [$model => sprintf('\%s%s\%s', app()->getNamespace(), 'Models', $model)];
            })
            ->filter(function ($class) {
                $reflection = new ReflectionClass($class);
                return $reflection->isSubclassOf(Model::class) &&
                    !$reflection->isAbstract() &&
                    in_array(HasReporting::class, array_keys($reflection->getTraits()));
            });
    }

    public static function getModelRelations($model)
    {
        return collect(get_class_methods($model))
            ->map(fn ($method) => new ReflectionMethod($model, $method))
            ->reject(
                fn (ReflectionMethod $method) => $method->isStatic()
                    || $method->isAbstract()
                    || $method->getDeclaringClass()->getName() === Model::class
            )
            ->filter(function (ReflectionMethod $method) {
                $file = new \SplFileObject($method->getFileName());
                $file->seek($method->getStartLine() - 1);
                $code = '';
                while ($file->key() < $method->getEndLine()) {
                    $code .= trim($file->current());
                    $file->next();
                }

                return collect(static::$relationMethods)
                    ->contains(fn ($relationMethod) => str_contains($code, '$this->'.$relationMethod.'('));
            })
            ->filter(function (ReflectionMethod $method) use ($model) {
                $relation = $method->invoke($model);

                $reflection = new ReflectionClass($relation->getRelated());
                return $reflection->isSubclassOf(Model::class) &&
                    !$reflection->isAbstract() &&
                    in_array(HasReporting::class, array_keys($reflection->getTraits()));
            })
            ->map(function (ReflectionMethod $method) use ($model) {
                $relation = $method->invoke($model);

                if (!$relation instanceof Relation) {
                    return null;
                }

                return [
                    'name' => $method->getName(),
                    'type' => Str::afterLast(get_class($relation), '\\'),
                    'related' => get_class($relation->getRelated()),
                ];
            })
            ->values();
    }

    public static function  getAttributes($model)
    {
        $connection = $model->getConnection();
        $schema = $connection->getDoctrineSchemaManager();
        static::registerTypeMappings($connection->getDoctrineConnection()->getDatabasePlatform());

        $database = $connection->getDatabaseName(); // database_name
        $table = $model->getTable(); // database_name.table
        // remove the database name from the table name
        $table = str_replace($database . '.', '', $table);
        $columns = $schema->listTableColumns($table, $database);
        $indexes = $schema->listTableIndexes($table, $database);

        return collect($columns)
            ->values()
            ->map(fn (Column $column) => [
                'name' => $column->getName(),
                'type' => static::getColumnType($column),
                'increments' => $column->getAutoincrement(),
                'nullable' => ! $column->getNotnull(),
                'default' => static::getColumnDefault($column, $model),
                'primary' => static::columnIsPrimary($column->getName(), $indexes),
                'unique' => static::columnIsUnique($column->getName(), $indexes),
                'foreign' => static::columnIsForeign($column->getName(), $indexes),
                'fillable' => $model->isFillable($column->getName()),
                'hidden' => static::attributeIsHidden($column->getName(), $model),
                'appended' => null,
                'cast' => static::getCastType($column->getName(), $model),
            ])
            ->merge(static::getVirtualAttributes($model, $columns));
    }

    protected static function getVirtualAttributes($model, $columns)
    {
        $class = new ReflectionClass($model);

        return collect($class->getMethods())
            ->reject(
                fn (ReflectionMethod $method) => $method->isStatic()
                    || $method->isAbstract()
                    || $method->getDeclaringClass()->getName() === Model::class
            )
            ->mapWithKeys(function (ReflectionMethod $method) use ($model) {
                if (preg_match('/^get(.+)Attribute$/', $method->getName(), $matches) === 1) {
                    return [Str::snake($matches[1]) => 'accessor'];
                } elseif ($model->hasAttributeMutator($method->getName())) {
                    return [Str::snake($method->getName()) => 'attribute'];
                } else {
                    return [];
                }
            })
            ->reject(fn ($cast, $name) => collect($columns)->has($name))
            ->map(fn ($cast, $name) => [
                'name' => $name,
                'type' => null,
                'increments' => false,
                'nullable' => null,
                'default' => null,
                'primary' => null,
                'unique' => null,
                'foreign' => null,
                'fillable' => $model->isFillable($name),
                'hidden' => static::attributeIsHidden($name, $model),
                'appended' => $model->hasAppended($name),
                'cast' => $cast,
            ])
            ->values();
    }

    protected static function registerTypeMappings(AbstractPlatform $platform)
    {
        foreach (static::$typeMappings as $type => $value) {
            $platform->registerDoctrineTypeMapping($type, $value);
        }
    }

    protected static function getColumnType($column)
    {
        $name = $column->getType()->getName();

        $unsigned = $column->getUnsigned() ? ' unsigned' : '';

        $details = match (get_class($column->getType())) {
            DecimalType::class => $column->getPrecision().','.$column->getScale(),
            default => $column->getLength(),
        };

        if ($details) {
            return sprintf('%s(%s)%s', $name, $details, $unsigned);
        }

        return sprintf('%s%s', $name, $unsigned);
    }

    protected static function getColumnDefault($column, $model)
    {
        $attributeDefault = $model->getAttributes()[$column->getName()] ?? null;

        return match (true) {
            $attributeDefault instanceof BackedEnum => $attributeDefault->value,
            $attributeDefault instanceof UnitEnum => $attributeDefault->name,
            default => $attributeDefault ?? $column->getDefault(),
        };
    }

    protected static function columnIsUnique($column, $indexes): bool
    {
        return collect($indexes)
            ->filter(fn (Index $index) => count($index->getColumns()) === 1 && $index->getColumns()[0] === $column)
            ->contains(fn (Index $index) => $index->isUnique());
    }

    protected static function columnIsPrimary($column, $indexes): bool
    {
        return collect($indexes)
            ->filter(fn (Index $index) => count($index->getColumns()) === 1 && $index->getColumns()[0] === $column)
            ->contains(fn (Index $index) => $index->isPrimary());
    }

    protected static function columnIsForeign($column, $indexes): bool
    {
        return collect($indexes)
            ->filter(fn (Index $index) => count($index->getColumns()) === 1 && $index->getColumns()[0] === $column)
            ->contains(fn (Index $index) => Str::contains($index->getName(), 'foreign'));
    }

    protected static function attributeIsHidden($attribute, $model): bool
    {
        if (count($model->getHidden()) > 0) {
            return in_array($attribute, $model->getHidden());
        }

        if (count($model->getVisible()) > 0) {
            return ! in_array($attribute, $model->getVisible());
        }

        return false;
    }

    protected static function getCastType($column, $model)
    {
        if ($model->hasGetMutator($column) || $model->hasSetMutator($column)) {
            return 'accessor';
        }

        if ($model->hasAttributeMutator($column)) {
            return 'attribute';
        }

        return static::getCastsWithDates($model)->get($column) ?? null;
    }

    protected static function getCastsWithDates($model)
    {
        return collect($model->getDates())
            ->filter()
            ->flip()
            ->map(fn () => 'datetime')
            ->merge($model->getCasts());
    }
}
