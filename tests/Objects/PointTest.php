<?php

namespace MatanYadaev\EloquentSpatial\Tests\Objects;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use InvalidArgumentException;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidLatitude;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidLongitude;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Tests\TestCase;
use MatanYadaev\EloquentSpatial\Tests\TestModels\TestPlace;

class PointTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_stores_point(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'point' => new Point(0, 90),
        ]);

        $this->assertInstanceOf(Point::class, $testPlace->point);
        $this->assertEquals(90, $testPlace->point->latitude);
        $this->assertEquals(0, $testPlace->point->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_stores_point_from_json(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'point' => Point::fromJson('{"type":"Point","coordinates":[0,90]}'),
        ]);

        $this->assertInstanceOf(Point::class, $testPlace->point);
        $this->assertEquals(90, $testPlace->point->latitude);
        $this->assertEquals(0, $testPlace->point->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_generates_point_geo_json(): void
    {
        $point = new Point(0, 90);

        $this->assertEquals('{"type":"Point","coordinates":[0,90]}', $point->toJson());
    }

    /** @test */
    public function it_throws_exception_when_generating_point_from_geo_json_without_coordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Point::fromJson('{"type":"Point","coordinates":[]}');
    }

    /** @test */
    public function it_throws_exception_when_creating_point_with_invalid_latitude(): void
    {
        $this->expectException(InvalidLatitude::class);

        new Point(1, 91);
    }

    /** @test */
    public function it_throws_exception_when_creating_point_with_invalid_longitude(): void
    {
        $this->expectException(InvalidLongitude::class);

        new Point(-181, 90);
    }
}
