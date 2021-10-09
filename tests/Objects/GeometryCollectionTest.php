<?php

namespace MatanYadaev\EloquentSpatial\Tests\Objects;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use InvalidArgumentException;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Tests\TestCase;
use MatanYadaev\EloquentSpatial\Tests\TestModels\TestPlace;

class GeometryCollectionTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_stores_geometry_collection(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'geometry_collection' => new GeometryCollection([
                new Polygon([
                    new LineString([
                        new Point(0, 90),
                        new Point(1, 89),
                        new Point(2, 88),
                        new Point(3, 87),
                        new Point(0, 90),
                    ]),
                ]),
                new Point(0, 90),
            ]),
        ]);

        $this->assertInstanceOf(GeometryCollection::class, $testPlace->geometry_collection);

        /** @var Polygon $polygon */
        $polygon = $testPlace->geometry_collection[0];
        $lineString = $polygon[0];

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

        /** @var Point $point */
        $point = $testPlace->geometry_collection[1];

        $this->assertEquals(90, $point->latitude);
        $this->assertEquals(0, $point->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_stores_geometry_collection_from_geo_json(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'geometry_collection' => GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[0,90],[1,89],[2,88],[3,87],[0,90]]]},{"type":"Point","coordinates":[0,90]}]}'),
        ]);

        $this->assertTrue($testPlace->geometry_collection instanceof GeometryCollection);

        /** @var Polygon $polygon */
        $polygon = $testPlace->geometry_collection[0];
        $lineString = $polygon[0];

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

        /** @var Point $point */
        $point = $testPlace->geometry_collection[1];

        $this->assertEquals(90, $point->latitude);
        $this->assertEquals(0, $point->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_stores_geometry_collection_from_feature_collection_geo_json(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'geometry_collection' => GeometryCollection::fromJson('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[0,90],[1,89],[2,88],[3,87],[0,90]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[0,90]}}]}'),
        ]);

        $this->assertTrue($testPlace->geometry_collection instanceof GeometryCollection);

        /** @var Polygon $polygon */
        $polygon = $testPlace->geometry_collection[0];
        $lineString = $polygon[0];

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

        /** @var Point $point */
        $point = $testPlace->geometry_collection[1];

        $this->assertEquals(90, $point->latitude);
        $this->assertEquals(0, $point->longitude);

        $this->assertDatabaseCount($testPlace->getTable(), 1);
    }

    /** @test */
    public function it_generates_geometry_collection_geo_json(): void
    {
        $geometryCollection = new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
            new Point(0, 90),
        ]);

        $this->assertEquals(
            '{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[0,90],[1,89],[2,88],[3,87],[0,90]]]},{"type":"Point","coordinates":[0,90]}]}',
            $geometryCollection->toJson()
        );
    }

    /** @test */
    public function it_generates_geometry_collection_feature_collection_json(): void
    {
        $geometryCollection = new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
            new Point(0, 90),
        ]);

        $this->assertEquals(
            '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[0,90],[1,89],[2,88],[3,87],[0,90]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[0,90]}}]}',
            $geometryCollection->toFeatureCollectionJson()
        );
    }

    /** @test */
    public function it_does_not_throw_exception_when_geometry_collection_has_0_geometries(): void
    {
        $geometryCollection = new GeometryCollection([]);

        $this->assertCount(0, $geometryCollection->getGeometries());
    }

    /** @test */
    public function it_throws_exception_when_geometry_collection_has_composed_by_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line
        new GeometryCollection([
            'invalid-value',
        ]);
    }

    /** @test */
    public function it_unsets_geometry_collection_item(): void
    {
        $geometryCollection = new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
            new Point(0, 90),
        ]);

        unset($geometryCollection[0]);

        $this->assertInstanceOf(Point::class, $geometryCollection[0]);
        $this->assertCount(1, $geometryCollection->getGeometries());
    }

    /** @test */
    public function it_unsets_geometry_collection_item_below_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $polygon = new Polygon([
            new LineString([
                new Point(0, 90),
                new Point(1, 89),
                new Point(2, 88),
                new Point(3, 87),
                new Point(0, 90),
            ]),
        ]);

        unset($polygon[0]);
    }

    /** @test */
    public function it_checks_if_geometry_collection_item_is_exists(): void
    {
        $geometryCollection = new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
            new Point(0, 90),
        ]);

        $this->assertTrue(isset($geometryCollection[0]));
        $this->assertTrue(isset($geometryCollection[1]));
        $this->assertFalse(isset($geometryCollection[2]));
    }

    /** @test */
    public function it_sets_valid_item_to_geometry_collection(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'polygon' => new Polygon([
                new LineString([
                    new Point(0, 90),
                    new Point(1, 89),
                    new Point(2, 88),
                    new Point(3, 87),
                    new Point(0, 90),
                ]),
            ]),
        ]);

        $testPlace->polygon[1] = new LineString([
            new Point(0, 90),
            new Point(1, 89),
            new Point(2, 88),
            new Point(3, 87),
            new Point(0, 90),
        ]);

        $testPlace->save();

        $testPlace->refresh();

        $this->assertInstanceOf(LineString::class, $testPlace->polygon[1]);
    }

    /** @test */
    public function it_sets_invalid_item_to_geometry_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $polygon = new Polygon([
            new LineString([
                new Point(0, 90),
                new Point(1, 89),
                new Point(2, 88),
                new Point(3, 87),
                new Point(0, 90),
            ]),
        ]);

        $polygon[1] = new Point(0, 90);
    }
}
