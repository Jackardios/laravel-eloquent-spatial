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

/** @var list<array{string, class-string<Type>, class-string<Type>}> $dataset */
$dataset = [
    ['point', GeometryType::class, PointType::class],
    ['point_geography', GeographyType::class, PointType::class],
    ['line_string', GeometryType::class, LineStringType::class],
    ['multi_point', GeometryType::class, MultiPointType::class],
    ['polygon', GeometryType::class, PolygonType::class],
    ['multi_line_string', GeometryType::class, MultiLineStringType::class],
    ['multi_polygon', GeometryType::class, MultiPolygonType::class],
    ['geometry_collection', GeometryType::class, GeometryCollectionType::class],
];

it('uses custom Doctrine types for spatial columns', function ($column, $postgresType, $mySqlType): void {
    $doctrineSchemaManager = DB::connection()->getDoctrineSchemaManager();

    $columns = $doctrineSchemaManager->listTableColumns('test_places');

    expect($columns[$column]->getType())->toBeInstanceOfOnPostgres($postgresType)->toBeInstanceOfOnMysql($mySqlType);
})->with($dataset)->skip(version_compare(Application::VERSION, '11.0.0', '>='));
