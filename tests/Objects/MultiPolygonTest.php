<?php

namespace MatanYadaev\EloquentSpatial\Tests\Objects;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use InvalidArgumentException;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\MultiPolygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Tests\TestCase;
use MatanYadaev\EloquentSpatial\Tests\TestModels\TestPlace;

class MultiPolygonTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_stores_multi_polygon(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'multi_polygon' => new MultiPolygon([
                new Polygon([
                    new LineString([
                        new Point(0, 90),
                        new Point(1, 89),
                        new Point(2, 88),
                        new Point(3, 87),
                        new Point(0, 90),
                    ]),
                ]),
            ]),
        ]);

        $this->assertTrue($testPlace->multi_polygon instanceof MultiPolygon);

        $lineString = $testPlace->multi_polygon[0][0];

        $this->assertEquals(90, $lineString[0]->latitude);
        $this->assertEquals(0, $lineString[0]->longitude);
        $this->assertEquals(89, $lineString[1]->latitude);
        $this->assertEquals(1, $lineString[1]->longitude);
        $this->assertEquals(88, $lineString[2]->latitude);
        $this->assertEquals(2, $lineString[2]->longitude);
        $this->assertEquals(87, $lineString[3]->latitude);
        $this->assertEquals(3, $lineString[3]->longitude);
        $this->assertEquals(90, $lineString[4]->latitude);
        $this->assertEquals(0, $lineString[4]->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_stores_multi_polygon_from_geo_json(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'multi_polygon' => MultiPolygon::fromJson('{"type":"MultiPolygon","coordinates":[[[[0,90],[1,89],[2,88],[3,87],[0,90]]]]}'),
        ]);

        $this->assertTrue($testPlace->multi_polygon instanceof MultiPolygon);

        $lineString = $testPlace->multi_polygon[0][0];

        $this->assertEquals(90, $lineString[0]->latitude);
        $this->assertEquals(0, $lineString[0]->longitude);
        $this->assertEquals(89, $lineString[1]->latitude);
        $this->assertEquals(1, $lineString[1]->longitude);
        $this->assertEquals(88, $lineString[2]->latitude);
        $this->assertEquals(2, $lineString[2]->longitude);
        $this->assertEquals(87, $lineString[3]->latitude);
        $this->assertEquals(3, $lineString[3]->longitude);
        $this->assertEquals(90, $lineString[4]->latitude);
        $this->assertEquals(0, $lineString[4]->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_generates_multi_polygon_geo_json(): void
    {
        $multiPolygon = new MultiPolygon([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
        ]);

        $this->assertEquals('{"type":"MultiPolygon","coordinates":[[[[0,90],[1,89],[2,88],[3,87],[0,90]]]]}', $multiPolygon->toJson());
    }

    /** @test */
    public function it_generates_multi_polygon_feature_collection_json(): void
    {
        $multiPolygon = new MultiPolygon([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
        ]);

        $this->assertEquals('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"MultiPolygon","coordinates":[[[[0,90],[1,89],[2,88],[3,87],[0,90]]]]}}]}', $multiPolygon->toFeatureCollectionJson());
    }

    /** @test */
    public function it_throws_exception_when_multi_polygon_has_0_polygons(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultiPolygon([]);
    }

    /** @test */
    public function it_throws_exception_when_multi_polygon_has_composed_by_point(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line
        new MultiPolygon([
            new Point(0, 0),
        ]);
    }
}
