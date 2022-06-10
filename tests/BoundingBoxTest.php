<?php

namespace MatanYadaev\EloquentSpatial\Tests;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MatanYadaev\EloquentSpatial\BoundingBox;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\MultiLineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Tests\TestModels\TestPlace;

class BoundingBoxTest extends TestCase
{
    /** @test */
    public function it_can_create_bounding_box(): void
    {
        $bbox = new BoundingBox(
            new Point(-30.618423, -12.751244),
            new Point(91.618423, 40.751244)
        );

        $this->assertEquals([
            'left' => -30.618423,
            'bottom' => -12.751244,
            'right' => 91.618423,
            'top' => 40.751244,
        ], $bbox->toArray());
    }

    /** @test */
    public function it_throws_exception_when_right_less_the_left(): void
    {
        $this->expectException(InvalidBoundingBoxPoints::class);

        new BoundingBox(
            new Point(91.618423, -12.751244),
            new Point(-30.618423, 40.751244)
        );
    }

    /** @test */
    public function it_throws_exception_when_top_less_the_bottom(): void
    {
        $this->expectException(InvalidBoundingBoxPoints::class);

        new BoundingBox(
            new Point(-30.618423, 40.751244),
            new Point(91.618423, -12.751244)
        );
    }

    /** @test */
    public function it_can_create_bounding_box_from_points_collection(): void
    {
        $bbox = BoundingBox::fromPoints(collect([
            new Point(-30.618423, 40.751244),
            new Point(-32.453251, 52.435631),
            new Point(-24.463204, 30.12345),
            new Point(1.421546, -12.575421),
            new Point(-4.618423, 40.751244),
        ]));

        $this->assertEquals([
            'left' => -32.453251,
            'bottom' => -12.575421,
            'right' => 1.421546,
            'top' => 52.435631,
        ], $bbox->toArray());
    }

    /** @test */
    public function it_can_create_bounding_box_from_points_array(): void
    {
        $bbox = BoundingBox::fromPoints([
            new Point(-30.618423, 40.751244),
            new Point(-32.453251, 52.435631),
            new Point(-24.463204, 30.12345),
            new Point(1.421546, -12.575421),
            new Point(-4.618423, 40.751244),
        ]);

        $this->assertEquals([
            'left' => -32.453251,
            'bottom' => -12.575421,
            'right' => 1.421546,
            'top' => 52.435631,
        ], $bbox->toArray());
    }

    /** @test */
    public function it_can_create_bounding_box_from_geometry_collection(): void
    {
        $bbox = BoundingBox::fromGeometryCollection(new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(-30.618423, 40.751244),
                    new Point(-32.453251, 52.435631),
                    new Point(-24.463204, 30.12345),
                    new Point(1.421546, -12.575421),
                    new Point(-4.618423, 40.751244),
                ]),
            ]),
            new Point(-16.342145, 54.547658),
            new MultiLineString([
                new LineString([
                    new Point(-31.435463, 23.876852),
                    new Point(-36.546231, 32.345323),
                    new Point(-12.876852, 39.125543),
                ]),
            ])
        ]));

        $this->assertEquals([
            'left' => -36.546231,
            'bottom' => -12.575421,
            'right' => 1.421546,
            'top' => 54.547658,
        ], $bbox->toArray());
    }

    /** @test */
    public function it_can_convert_bounding_box_to_polygon(): void
    {
        $bbox = BoundingBox::fromGeometryCollection(new GeometryCollection([
            new Polygon([
                new LineString([
                    new Point(-30.618423, 40.751244),
                    new Point(-32.453251, 52.435631),
                    new Point(-24.463204, 30.12345),
                    new Point(1.421546, -12.575421),
                    new Point(-4.618423, 40.751244),
                ]),
            ]),
            new Point(-16.342145, 54.547658),
            new MultiLineString([
                new LineString([
                    new Point(-31.435463, 23.876852),
                    new Point(-36.546231, 32.345323),
                    new Point(-12.876852, 39.125543),
                ]),
            ])
        ]));

        $this->assertEquals([[
            [-36.546231, 54.547658],
            [1.421546, 54.547658],
            [1.421546, -12.575421],
            [-36.546231, -12.575421],
            [-36.546231, 54.547658],
        ]], $bbox->toPolygon()->getCoordinates());
    }

    /** @test */
    public function it_serializes_and_deserializes_bounding_box_object(): void
    {
        $boundingBox = new BoundingBox(
            new Point(-30.618423, -12.751244),
            new Point(91.618423, 40.751244)
        );

        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'bounding_box' => $boundingBox,
        ])->fresh();

        $this->assertEquals($boundingBox, $testPlace->bounding_box);
    }

    /** @test */
    public function it_throws_exception_when_serializing_invalid_bounding_box_object(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestPlace::factory()->make([
            'bounding_box' => new Point(0, 90),
        ]);
    }

    /** @test */
    public function it_throws_exception_when_serializing_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestPlace::factory()->make([
            'bounding_box' => 'not-a-bounding-box-object',
        ]);
    }

    /** @test */
    public function it_throws_exception_when_deserializing_invalid_bounding_box_object(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestPlace::factory()->create([
            'bounding_box' => DB::raw('POINT(0, 90)'),
        ]);

        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::firstOrFail();

        $testPlace->getAttribute('bounding_box');
    }

    /** @test */
    public function it_serializes_and_deserializes_null(): void
    {
        /** @var TestPlace $testPlace */
        $testPlace = TestPlace::factory()->create([
            'bounding_box' => null,
        ])->fresh();

        $this->assertEquals(null, $testPlace->bounding_box);
    }
}
