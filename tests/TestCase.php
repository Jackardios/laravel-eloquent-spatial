<?php

namespace Jackardios\EloquentSpatial\Tests;

use Illuminate\Support\ServiceProvider;
use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\EloquentSpatialServiceProvider;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetEloquentSpatial();
    }

    protected function tearDown(): void
    {
        Geometry::flushMacros();

        parent::tearDown();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            version_compare($this->app->version(), '11.0.0', '>=')
                ? __DIR__.'/database/migrations'
                : __DIR__.'/database/migrations-laravel-10'
        );
    }

    /**
     * @return class-string<ServiceProvider>[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            EloquentSpatialServiceProvider::class,
        ];
    }

    protected function resetEloquentSpatial(): void
    {
        EloquentSpatial::useGeometryCollection(GeometryCollection::class);
        EloquentSpatial::useLineString(LineString::class);
        EloquentSpatial::useMultiLineString(MultiLineString::class);
        EloquentSpatial::useMultiPoint(MultiPoint::class);
        EloquentSpatial::useMultiPolygon(MultiPolygon::class);
        EloquentSpatial::usePoint(Point::class);
        EloquentSpatial::usePolygon(Polygon::class);
        EloquentSpatial::setDefaultSrid(0);
    }
}
