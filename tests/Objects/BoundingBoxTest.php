<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use MatanYadaev\EloquentSpatial\Objects\BoundingBox;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\MultiLineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Tests\TestModels\TestPlace;

uses(DatabaseMigrations::class);

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

it('throws exception when right less than the left', function () {
  expect(function () {
    new BoundingBox(
      new Point(91.618423, -12.751244),
      new Point(-30.618423, 40.751244)
    );
  })->toThrow(InvalidBoundingBoxPoints::class);
});

it('throws exception when top less than the bottom', function () {
  expect(function () {
    new BoundingBox(
      new Point(-30.618423, 40.751244),
      new Point(91.618423, -12.751244)
    );
  })->toThrow(InvalidBoundingBoxPoints::class);
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
    'top' => 40.756244
  ]);
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
    ])
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
    ])
  ]));

  expect($bbox->toPolygon()->getCoordinates())->toBe([
    [
      [-36.546231, 54.547658],
      [1.421546, 54.547658],
      [1.421546, -12.575421],
      [-36.546231, -12.575421],
      [-36.546231, 54.547658],
    ]
  ]);
});

it('serializes and deserializes bounding box object', function () {
  $boundingBox = new BoundingBox(
    new Point(-30.618423, -12.751244),
    new Point(91.618423, 40.751244)
  );

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

it('throws exception when deserializing invalid bounding box object', function () {
  expect(function () {
    TestPlace::factory()->create([
      'bounding_box' => DB::raw('POINT(0, 90)'),
    ]);

    $testPlace = TestPlace::firstOrFail();

    $testPlace->getAttribute('bounding_box');
  })->toThrow(InvalidArgumentException::class);
});

it('serializes and deserializes null', function () {
  $testPlace = TestPlace::factory()->create([
    'bounding_box' => null,
  ])->fresh();

  expect($testPlace->bounding_box)->toBeNull();
});
