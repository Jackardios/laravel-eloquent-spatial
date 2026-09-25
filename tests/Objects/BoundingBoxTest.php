<?php

use Jackardios\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use Jackardios\EloquentSpatial\Exceptions\InvalidGeometry;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;

it('can create bounding box', function () {
    $bbox = new BoundingBox(
        new Point(-30.618423, -12.751244),
        new Point(91.618423, 40.751244)
    );

    expect($bbox->toArray())->toBe([
        'left' => -30.618423,
        'bottom' => -12.751244,
        'right' => 91.618423,
        'top' => 40.751244,
    ]);
});

it('can create antimeridian-crossing bounding box', function () {
    $bbox = new BoundingBox(
        new Point(170, 50),
        new Point(-170, 60)
    );

    expect($bbox->toArray())->toBe([
        'left' => 170.0,
        'bottom' => 50.0,
        'right' => -170.0,
        'top' => 60.0,
    ]);
    expect($bbox->crossesAntimeridian())->toBeTrue();
});

it('normal bbox does not cross antimeridian', function () {
    $bbox = new BoundingBox(
        new Point(-30, -12),
        new Point(91, 40)
    );

    expect($bbox->crossesAntimeridian())->toBeFalse();
});

it('throws exception when top less than the bottom', function () {
    expect(function () {
        new BoundingBox(
            new Point(-30.618423, 40.751244),
            new Point(91.618423, -12.751244)
        );
    })->toThrow(InvalidBoundingBoxPoints::class);
});

it('rejects points outside the longitude and latitude ranges', function (Point $leftBottom, Point $rightTop, string $message): void {
    expect(fn () => new BoundingBox($leftBottom, $rightTop))->toThrow(InvalidBoundingBoxPoints::class, $message);
})->with([
    'left' => [new Point(-180.1, 0, 3857), new Point(10, 10), 'Bounding box longitudes must be between -180 and 180'],
    'right' => [new Point(0, 0), new Point(180.1, 10, 3857), 'Bounding box longitudes must be between -180 and 180'],
    'bottom' => [new Point(0, -90.1, 3857), new Point(10, 10), 'Bounding box latitudes must be between -90 and 90'],
    'top' => [new Point(0, 0), new Point(10, 90.1, 3857), 'Bounding box latitudes must be between -90 and 90'],
]);

it('rejects projected points', function (): void {
    $points = [new Point(-20037508.34, -1000000, 3857), new Point(20037508.34, 1000000, 3857)];

    expect(fn () => BoundingBox::fromPoints($points))->toThrow(InvalidBoundingBoxPoints::class, 'Bounding box longitudes must be between -180 and 180');
});

it('accepts the whole longitude and latitude ranges', function (): void {
    $bbox = new BoundingBox(new Point(-180, -90, 3857), new Point(180, 90, 3857));

    expect($bbox->toArray())->toBe(['left' => -180.0, 'bottom' => -90.0, 'right' => 180.0, 'top' => 90.0]);
});

it('can create bounding box from points collection', function () {
    $bbox = BoundingBox::fromPoints(collect([
        new Point(-30.618423, 40.751244),
        new Point(-32.453251, 52.435631),
        new Point(-24.463204, 30.12345),
        new Point(1.421546, -12.575421),
        new Point(-4.618423, 40.751244),
    ]));

    expect($bbox->toArray())->toBe([
        'left' => -32.453251,
        'bottom' => -12.575421,
        'right' => 1.421546,
        'top' => 52.435631,
    ]);
});

it('can create bounding box from points array', function () {
    $bbox = BoundingBox::fromPoints([
        new Point(-30.618423, 40.751244),
        new Point(-32.453251, 52.435631),
        new Point(-24.463204, 30.12345),
        new Point(1.421546, -12.575421),
        new Point(-4.618423, 40.751244),
    ]);

    expect($bbox->toArray())->toBe([
        'left' => -32.453251,
        'bottom' => -12.575421,
        'right' => 1.421546,
        'top' => 52.435631,
    ]);
});

it('can create bounding box with min padding', function () {
    $bbox = BoundingBox::fromPoints([
        new Point(-30.458423, 40.751244),
        new Point(-30.453251, 52.435631),
        new Point(-30.453251, 32.235461),
        new Point(-30.453204, 30.12345),
    ], 0.01);

    expect($bbox->toArray())->toBe([
        'left' => -30.4608135,
        'bottom' => 30.12345,
        'right' => -30.4508135,
        'top' => 52.435631,
    ]);

    $bbox = BoundingBox::fromPoints([
        new Point(-30.458423, 40.751244),
        new Point(-30.458423, 40.751244),
        new Point(-30.458423, 40.751244),
        new Point(-30.458423, 40.751244),
        new Point(-30.458423, 40.751244),
    ], 0.01);

    expect($bbox->toArray())->toBe([
        'left' => -30.463423,
        'bottom' => 40.746244,
        'right' => -30.453423,
        'top' => 40.756244,
    ]);
});

it('wraps padded longitudes across the antimeridian', function (float $longitude, float $left, float $right): void {
    $bbox = BoundingBox::fromPoints([new Point($longitude, 0), new Point($longitude, 1)], 1.0);

    expect($bbox->crossesAntimeridian())->toBeTrue()
        ->and($bbox->getLeftBottom()->longitude)->toEqualWithDelta($left, 1e-9)
        ->and($bbox->getRightTop()->longitude)->toEqualWithDelta($right, 1e-9);
})->with([
    'east edge' => [179.8, 179.3, -179.7],
    'west edge' => [-179.8, 179.7, -179.3],
]);

it('throws when creating a bounding box from an unsupported geometry', function (): void {
    $geometry = new class extends Geometry
    {
        public function toWkt(): string
        {
            return 'CURVE EMPTY';
        }

        public function getWktData(): string
        {
            return 'EMPTY';
        }

        public function getCoordinates(): array
        {
            return [];
        }
    };

    expect(fn () => BoundingBox::fromGeometry($geometry))
        ->toThrow(InvalidGeometry::class, 'cannot create bounding box from '.$geometry::class);
});

it('throws exception when min padding is negative', function () {
    expect(function () {
        BoundingBox::fromPoints([
            new Point(0, 0),
            new Point(1, 1),
        ], -0.1);
    })->toThrow(InvalidArgumentException::class, 'minPadding must be non-negative');
});

it('can create bounding box from geometry collection', function () {
    $bbox = BoundingBox::fromGeometry(new GeometryCollection([
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
        ]),
    ]));

    expect($bbox->toArray())->toBe([
        'left' => -36.546231,
        'bottom' => -12.575421,
        'right' => 1.421546,
        'top' => 54.547658,
    ]);
});

it('can convert bounding box to polygon', function () {
    $bbox = BoundingBox::fromGeometry(new GeometryCollection([
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
        ]),
    ]));

    expect($bbox->toPolygon()->getCoordinates())->toBe([
        [
            [-36.546231, -12.575421],
            [1.421546, -12.575421],
            [1.421546, 54.547658],
            [-36.546231, 54.547658],
            [-36.546231, -12.575421],
        ],
    ]);
});

it('serializes and deserializes bounding box object', function () {
    $boundingBox = new BoundingBox(
        new Point(-30.618423, -12.751244),
        new Point(91.618423, 40.751244)
    );

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => $boundingBox,
    ])->fresh();

    expect($testPlace->bounding_box)->toEqual($boundingBox);
});

it('throws exception when serializing invalid bounding box object', function () {
    expect(function () {
        TestPlace::factory()->make([
            'bounding_box' => new Point(0, 90),
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when serializing invalid type', function () {
    expect(function () {
        TestPlace::factory()->make([
            'bounding_box' => 'not-a-bounding-box-object',
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('serializes and deserializes null', function () {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => null,
    ])->fresh();

    expect($testPlace->bounding_box)->toBeNull();
});

it('toPolygon throws for antimeridian-crossing bbox', function () {
    $bbox = new BoundingBox(
        new Point(170, 50),
        new Point(-170, 60)
    );

    expect(fn () => $bbox->toPolygon())->toThrow(InvalidArgumentException::class);
});

it('toGeometry returns Polygon for normal bbox', function () {
    $bbox = new BoundingBox(
        new Point(-30, -12),
        new Point(91, 40)
    );

    $geometry = $bbox->toGeometry();

    expect($geometry)->toBeInstanceOf(Polygon::class);
    expect($geometry->getCoordinates())->toBe([
        [
            [-30.0, -12.0],
            [91.0, -12.0],
            [91.0, 40.0],
            [-30.0, 40.0],
            [-30.0, -12.0],
        ],
    ]);
});

it('toGeometry returns MultiPolygon for antimeridian-crossing bbox', function () {
    $bbox = new BoundingBox(
        new Point(170, 50),
        new Point(-170, 60)
    );

    $geometry = $bbox->toGeometry();

    expect($geometry)->toBeInstanceOf(MultiPolygon::class);
    expect($geometry->getCoordinates())->toBe([
        [
            [
                [170.0, 50.0],
                [180.0, 50.0],
                [180.0, 60.0],
                [170.0, 60.0],
                [170.0, 50.0],
            ],
        ],
        [
            [
                [-180.0, 50.0],
                [-170.0, 50.0],
                [-170.0, 60.0],
                [-180.0, 60.0],
                [-180.0, 50.0],
            ],
        ],
    ]);
});

it('fromPoints detects antimeridian crossing', function () {
    $bbox = BoundingBox::fromPoints([
        new Point(175, 50),
        new Point(-175, 55),
        new Point(170, 60),
    ]);

    expect($bbox->crossesAntimeridian())->toBeTrue();
    expect($bbox->toArray())->toBe([
        'left' => 170.0,
        'bottom' => 50.0,
        'right' => -175.0,
        'top' => 60.0,
    ]);
});

it('fromPoints does not detect antimeridian for points not crossing', function () {
    $bbox = BoundingBox::fromPoints([
        new Point(-30, 40),
        new Point(-32, 52),
        new Point(1, -12),
    ]);

    expect($bbox->crossesAntimeridian())->toBeFalse();
});

it('serializes and deserializes antimeridian-crossing bounding box', function () {
    $boundingBox = new BoundingBox(
        new Point(170, 50),
        new Point(-170, 60)
    );

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => $boundingBox,
    ])->fresh();

    expect($testPlace->bounding_box)->toBeInstanceOf(BoundingBox::class);
    expect($testPlace->bounding_box->crossesAntimeridian())->toBeTrue();
});

it('throws exception when creating bounding box from empty points', function () {
    expect(function () {
        BoundingBox::fromPoints([]);
    })->toThrow(InvalidArgumentException::class, 'cannot create bounding box from empty points');
});

it('can create bounding box from LineString via GeometryCollection inheritance', function () {
    // LineString extends GeometryCollection, so it's supported
    $lineString = new LineString([
        new Point(0, 0),
        new Point(10, 20),
    ]);

    $bbox = BoundingBox::fromGeometry($lineString);

    expect($bbox->toArray())->toBe([
        'left' => 0.0,
        'bottom' => 0.0,
        'right' => 10.0,
        'top' => 20.0,
    ]);
});

it('creates a bounding box without height from a single point or a horizontal line', function (array $expected, Point ...$points) {
    expect(BoundingBox::fromPoints(array_values($points))->toArray())->toBe($expected);
})->with([
    'single point' => [['left' => 10.0, 'bottom' => 20.0, 'right' => 10.0, 'top' => 20.0], new Point(10, 20)],
    'horizontal line' => [['left' => 10.0, 'bottom' => 20.0, 'right' => 30.0, 'top' => 20.0], new Point(10, 20), new Point(30, 20)],
]);

it('accepts the same top and bottom', function () {
    $bbox = BoundingBox::fromArray(['left' => 1, 'bottom' => 2, 'right' => 3, 'top' => 2]);

    expect($bbox->toArray())->toBe(['left' => 1.0, 'bottom' => 2.0, 'right' => 3.0, 'top' => 2.0]);
});

it('stores and reads a bounding box without height', function (string $attribute) {
    $bbox = BoundingBox::fromPoints([new Point(10, 20), new Point(30, 20)]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([$attribute => $bbox])->fresh();

    expect($testPlace->{$attribute}?->toArray())->toBe($bbox->toArray());
})->with(['bounding_box', 'bounding_box_json']);

it('covers the whole longitude range when the padding is at least 360 degrees', function (float $minPadding) {
    $bbox = BoundingBox::fromPoints([new Point(10, 20)], $minPadding);

    expect($bbox->toArray())->toBe(['left' => -180.0, 'bottom' => -90.0, 'right' => 180.0, 'top' => 90.0]);
})->with(['360' => [360.0], '400' => [400.0], '1e300' => [1.0E300]]);

it('pads up to just under 360 degrees across the antimeridian', function () {
    $bbox = BoundingBox::fromPoints([new Point(10, 20)], 359);

    expect($bbox->toArray())->toBe(['left' => -169.5, 'bottom' => -90.0, 'right' => -170.5, 'top' => 90.0])
        ->and($bbox->crossesAntimeridian())->toBeTrue();
});

it('keeps a padded longitude of exactly 180 degrees', function () {
    $bbox = BoundingBox::fromPoints([new Point(170, 0), new Point(170, 1)], 20);

    expect($bbox->toArray())->toBe(['left' => 160.0, 'bottom' => -9.5, 'right' => 180.0, 'top' => 10.5])
        ->and($bbox->crossesAntimeridian())->toBeFalse();
});

it('rejects a padding that is not finite', function (float $minPadding) {
    expect(fn () => BoundingBox::fromPoints([new Point(0, 0)], $minPadding))
        ->toThrow(InvalidArgumentException::class, 'minPadding must be non-negative and finite');
})->with(['NaN' => [NAN], 'infinity' => [INF]]);

it('can create bounding box from single point with padding', function () {
    $point = new Point(10, 20);
    $bbox = BoundingBox::fromGeometry($point, 2.0);

    expect($bbox->toArray())->toBe([
        'left' => 9.0,
        'bottom' => 19.0,
        'right' => 11.0,
        'top' => 21.0,
    ]);
});

it('converts bounding box to JSON string', function () {
    $bbox = new BoundingBox(
        new Point(-30, -12),
        new Point(91, 40)
    );

    $json = $bbox->toJson();

    expect($json)->toBe('{"left":-30,"bottom":-12,"right":91,"top":40}');
});

it('converts bounding box to string', function () {
    $bbox = new BoundingBox(
        new Point(-30, -12),
        new Point(91, 40)
    );

    expect((string) $bbox)->toBe('{"left":-30,"bottom":-12,"right":91,"top":40}');
});

it('provides getters for corner points', function () {
    $leftBottom = new Point(-30, -12);
    $rightTop = new Point(91, 40);
    $bbox = new BoundingBox($leftBottom, $rightTop);

    expect($bbox->getLeftBottom())->toEqual($leftBottom);
    expect($bbox->getLeftBottom())->not->toBe($leftBottom);
    expect($bbox->getRightTop())->toEqual($rightTop);
    expect($bbox->getRightTop())->not->toBe($rightTop);
});

it('clamps latitude to valid range when applying padding near poles', function () {
    // Point near north pole
    $bbox = BoundingBox::fromPoints([
        new Point(10, 89),
    ], 5.0);

    expect($bbox->toArray()['top'])->toBe(90.0);
    expect($bbox->toArray()['bottom'])->toBe(86.5);

    // Point near south pole
    $bbox = BoundingBox::fromPoints([
        new Point(10, -89),
    ], 5.0);

    expect($bbox->toArray()['bottom'])->toBe(-90.0);
    expect($bbox->toArray()['top'])->toBe(-86.5);
});

it('creates bounding box from array', function () {
    $bbox = BoundingBox::fromArray([
        'left' => -30.0,
        'bottom' => -12.0,
        'right' => 91.0,
        'top' => 40.0,
    ]);

    expect($bbox->toArray())->toBe([
        'left' => -30.0,
        'bottom' => -12.0,
        'right' => 91.0,
        'top' => 40.0,
    ]);
});

it('throws exception when creating from array with missing keys', function () {
    expect(function () {
        BoundingBox::fromArray([
            'left' => -30.0,
            'bottom' => -12.0,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Array must contain keys: left, bottom, right, top');
});

it('throws exception when creating from array with non-numeric values', function () {
    expect(function () {
        BoundingBox::fromArray([
            'left' => 'invalid',
            'bottom' => -12.0,
            'right' => 91.0,
            'top' => 40.0,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Array values for left, bottom, right, top must be numeric');
});

it('serializes and deserializes bounding box from array', function () {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => [
            'left' => -30.0,
            'bottom' => -12.0,
            'right' => 91.0,
            'top' => 40.0,
        ],
    ])->fresh();

    expect($testPlace->bounding_box)->toBeInstanceOf(BoundingBox::class);
    expect($testPlace->bounding_box->toArray())->toBe([
        'left' => -30.0,
        'bottom' => -12.0,
        'right' => 91.0,
        'top' => 40.0,
    ]);
});

it('roundtrips bounding box through toArray and fromArray', function () {
    $original = new BoundingBox(
        new Point(-30.618423, -12.751244),
        new Point(91.618423, 40.751244)
    );

    $recreated = BoundingBox::fromArray($original->toArray());

    expect($recreated->toArray())->toBe($original->toArray());
});

// JSON cast round trips

it('serializes and deserializes bounding box with json cast', function () {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box_json' => new BoundingBox(
            new Point(-30.618423, -12.751244),
            new Point(91.618423, 40.751244)
        ),
    ])->fresh();

    expect($testPlace->bounding_box_json)->toBeInstanceOf(BoundingBox::class);
    expect($testPlace->bounding_box_json->toArray())->toBe([
        'left' => -30.618423,
        'bottom' => -12.751244,
        'right' => 91.618423,
        'top' => 40.751244,
    ]);
});

it('serializes and deserializes null with json cast', function () {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box_json' => null,
    ])->fresh();

    expect($testPlace->bounding_box_json)->toBeNull();
});

it('serializes bounding box from array with json cast', function () {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box_json' => [
            'left' => -30.0,
            'bottom' => -12.0,
            'right' => 91.0,
            'top' => 40.0,
        ],
    ])->fresh();

    expect($testPlace->bounding_box_json)->toBeInstanceOf(BoundingBox::class);
    expect($testPlace->bounding_box_json->toArray())->toBe([
        'left' => -30.0,
        'bottom' => -12.0,
        'right' => 91.0,
        'top' => 40.0,
    ]);
});

it('is not changed through its points', function () {
    $leftBottom = new Point(10, 20);
    $rightTop = new Point(30, 40);
    $bbox = new BoundingBox($leftBottom, $rightTop);

    $leftBottom->latitude = 50;
    $rightTop->longitude = 0;
    $bbox->getLeftBottom()->latitude = 50;
    $bbox->getRightTop()->longitude = 0;

    expect($bbox->toArray())->toBe(['left' => 10.0, 'bottom' => 20.0, 'right' => 30.0, 'top' => 40.0]);
});
