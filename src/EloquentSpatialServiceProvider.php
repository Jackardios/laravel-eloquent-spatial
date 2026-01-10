<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseServiceProvider;
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

class EloquentSpatialServiceProvider extends DatabaseServiceProvider
{
    // @codeCoverageIgnoreStart
    public function boot(): void
    {
        if (version_compare(Application::VERSION, '11.0.0', '>=')) {
            return;
        }

        /** @var Connection $connection */
        $connection = DB::connection();

        if ($connection->isDoctrineAvailable()) {
            $this->registerDoctrineTypes($connection);
        }
    }

    protected function registerDoctrineTypes(Connection $connection): void
    {
        $geometries = [
            'point' => PointType::class,
            'linestring' => LineStringType::class,
            'multipoint' => MultiPointType::class,
            'polygon' => PolygonType::class,
            'multilinestring' => MultiLineStringType::class,
            'multipolygon' => MultiPolygonType::class,
            'geometrycollection' => GeometryCollectionType::class,
            'geomcollection' => GeometryCollectionType::class,
            'geography' => GeographyType::class,
            'geometry' => GeometryType::class,
        ];

        foreach ($geometries as $type => $class) {
            DB::registerDoctrineType($class, $type, $type);
            $connection->registerDoctrineType($class, $type, $type);
        }
    }
    // @codeCoverageIgnoreEnd
}
