<?php

use Doctrine\DBAL\Types\Type;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\Doctrine\GeographyType;
use Jackardios\EloquentSpatial\Doctrine\GeometryCollectionType;
use Jackardios\EloquentSpatial\Doctrine\GeometryType;
use Jackardios\EloquentSpatial\Doctrine\LineStringType;
use Jackardios\EloquentSpatial\Doctrine\MultiLineStringType;
use Jackardios\EloquentSpatial\Doctrine\MultiPointType;
use Jackardios\EloquentSpatial\Doctrine\MultiPolygonType;
use Jackardios\EloquentSpatial\Doctrine\PointType;
use Jackardios\EloquentSpatial\Doctrine\PolygonType;

/** @var array{column: string, postgresType: class-string<Type>, mySqlType: class-string<Type>} $typeClass */
$dataset = [
    [
        'column' => 'point',
        'postgresType' => GeometryType::class,
        'mySqlType' => PointType::class,
    ],
    [
        'column' => 'point_geography',
        'postgresType' => GeographyType::class,
        'mySqlType' => PointType::class,
    ],
    [
        'column' => 'line_string',
        'postgresType' => GeometryType::class,
        'mySqlType' => LineStringType::class,
    ],
    [
        'column' => 'multi_point',
        'postgresType' => GeometryType::class,
        'mySqlType' => MultiPointType::class,
    ],
    [
        'column' => 'polygon',
        'postgresType' => GeometryType::class,
        'mySqlType' => PolygonType::class,
    ],
    [
        'column' => 'multi_line_string',
        'postgresType' => GeometryType::class,
        'mySqlType' => MultiLineStringType::class,
    ],
    [
        'column' => 'multi_polygon',
        'postgresType' => GeometryType::class,
        'mySqlType' => MultiPolygonType::class,
    ],
    [
        'column' => 'geometry_collection',
        'postgresType' => GeometryType::class,
        'mySqlType' => GeometryCollectionType::class,
    ],
];

it('uses custom Doctrine types for spatial columns', function ($column, $postgresType, $mySqlType): void {
    $doctrineSchemaManager = DB::connection()->getDoctrineSchemaManager();

    $columns = $doctrineSchemaManager->listTableColumns('test_places');

    expect($columns[$column]->getType())->toBeInstanceOfOnPostgres($postgresType)->toBeInstanceOfOnMysql($mySqlType);
})->with($dataset)->skip(version_compare(Application::VERSION, '11.0.0', '>='));
