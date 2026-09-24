<?php

namespace Jackardios\EloquentSpatial\Tests;

use Illuminate\Contracts\Console\Kernel;
use Jackardios\EloquentSpatial\EloquentSpatial;
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

    /**
     * Runs once per process, after RefreshDatabase has refreshed the database.
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        $this->app->make(Kernel::class)->call('migrate', [
            '--path' => __DIR__.'/database/migrations',
            '--realpath' => true,
        ]);
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
