<?php

namespace Jackardios\EloquentSpatial\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\EloquentSpatialServiceProvider;
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

        $this->resetGeometryClasses();

        // @phpstan-ignore-next-line
        if (version_compare(Application::VERSION, '11.0.0', '>=')) {
            $this->loadMigrationsFrom(__DIR__.'/database/migrations-laravel->=11');
        } else {
            $this->loadMigrationsFrom(__DIR__.'/database/migrations-laravel-<=10');
        }
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

    protected function resetGeometryClasses(): void
    {
        EloquentSpatial::useGeometryCollection(GeometryCollection::class);
        EloquentSpatial::useLineString(LineString::class);
        EloquentSpatial::useMultiLineString(MultiLineString::class);
        EloquentSpatial::useMultiPoint(MultiPoint::class);
        EloquentSpatial::useMultiPolygon(MultiPolygon::class);
        EloquentSpatial::usePoint(Point::class);
        EloquentSpatial::usePolygon(Polygon::class);
    }
}
