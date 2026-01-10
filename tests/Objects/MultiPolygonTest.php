<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPolygon;

it('creates a model record with multi polygon', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['multi_polygon' => $multiPolygon]);

    expect($testPlace->multi_polygon)->toBeInstanceOf(MultiPolygon::class);
    expect($testPlace->multi_polygon)->toEqual($multiPolygon);
});

it('creates a model record with multi polygon with SRID integer', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['multi_polygon' => $multiPolygon]);

    expect($testPlace->multi_polygon->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with multi polygon with SRID enum', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['multi_polygon' => $multiPolygon]);

    expect($testPlace->multi_polygon->srid)->toBe(Srid::WGS84->value);
});

it('creates multi polygon from JSON', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $multiPolygonFromJson = MultiPolygon::fromJson('{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[178,2],[177,3],[180,0]]]]}');

    expect($multiPolygonFromJson)->toEqual($multiPolygon);
});

it('creates multi polygon with SRID from JSON', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84->value);

    $multiPolygonFromJson = MultiPolygon::fromJson('{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[178,2],[177,3],[180,0]]]]}', Srid::WGS84->value);

    expect($multiPolygonFromJson)->toEqual($multiPolygon);
});

it('creates multi polygon from array', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $multiPolygonFromJson = MultiPolygon::fromArray(['type' => 'MultiPolygon', 'coordinates' => [[[[180, 0], [179, 1], [178, 2], [177, 3], [180, 0]]]]]);

    expect($multiPolygonFromJson)->toEqual($multiPolygon);
});

it('creates multi polygon with SRID from array', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84->value);

    $multiPolygonFromJson = MultiPolygon::fromArray(['type' => 'MultiPolygon', 'coordinates' => [[[[180, 0], [179, 1], [178, 2], [177, 3], [180, 0]]]]], Srid::WGS84->value);

    expect($multiPolygonFromJson)->toEqual($multiPolygon);
});

it('generates multi polygon JSON', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $json = $multiPolygon->toJson();

    $expectedJson = '{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[178,2],[177,3],[180,0]]]]}';
    expect($json)->toBe($expectedJson);
});

it('generates multi polygon feature collection JSON', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $featureCollectionJson = $multiPolygon->toFeatureCollectionJson();

    $expectedFeatureCollectionJson = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[178,2],[177,3],[180,0]]]]}}]}';
    expect($featureCollectionJson)->toBe($expectedFeatureCollectionJson);
});

it('creates multi polygon from WKT', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $multiPolygonFromWkt = MultiPolygon::fromWkt('MULTIPOLYGON(((180 0, 179 1, 178 2, 177 3, 180 0)))');

    expect($multiPolygonFromWkt)->toEqual($multiPolygon);
});

it('creates multi polygon with SRID from WKT', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84->value);

    $multiPolygonFromWkt = MultiPolygon::fromWkt('MULTIPOLYGON(((180 0, 179 1, 178 2, 177 3, 180 0)))', Srid::WGS84->value);

    expect($multiPolygonFromWkt)->toEqual($multiPolygon);
});

it('generates multi polygon WKT', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $wkt = $multiPolygon->toWkt();

    $expectedWkt = 'MULTIPOLYGON(((180 0, 179 1, 178 2, 177 3, 180 0)))';
    expect($wkt)->toBe($expectedWkt);
});

it('creates multi polygon from WKB', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    $multiPolygonFromWkb = MultiPolygon::fromWkb($multiPolygon->toWkb());

    expect($multiPolygonFromWkb)->toEqual($multiPolygon);
});

it('creates multi polygon with SRID from WKB', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], Srid::WGS84->value);

    $multiPolygonFromWkb = MultiPolygon::fromWkb($multiPolygon->toWkb());

    expect($multiPolygonFromWkb)->toEqual($multiPolygon);
});

it('throws exception when multi polygon has no polygons', function (): void {
    expect(function (): void {
        new MultiPolygon([]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when creating multi polygon from incorrect geometry', function (): void {
    expect(function (): void {
        // @phpstan-ignore-next-line
        new MultiPolygon([
            new Point(0, 0),
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('casts a MultiPolygon to a string', function (): void {
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    expect($multiPolygon->__toString())->toEqual('MULTIPOLYGON(((180 0, 179 1, 178 2, 177 3, 180 0)))');
});

it('adds a macro toMultiPolygon', function (): void {
    Geometry::macro('getName', function (): string {
        /** @var Geometry $this */
        return class_basename($this);
    });

    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ]);

    // @phpstan-ignore-next-line
    expect($multiPolygon->getName())->toBe('MultiPolygon');
});

it('uses an extended MultiPolygon class', function (): void {
    // Arrange
    EloquentSpatial::useMultiPolygon(ExtendedMultiPolygon::class);
    $multiPolygon = new ExtendedMultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], 4326);

    // Act
    /** @var TestExtendedPlace $testPlace */
    $testPlace = TestExtendedPlace::factory()->create(['multi_polygon' => $multiPolygon])->fresh();

    // Assert
    expect($testPlace->multi_polygon)->toBeInstanceOf(ExtendedMultiPolygon::class);
    expect($testPlace->multi_polygon)->toEqual($multiPolygon);
});

it('throws exception when storing a record with regular MultiPolygon instead of the extended one', function (): void {
    // Arrange
    EloquentSpatial::useMultiPolygon(ExtendedMultiPolygon::class);
    $multiPolygon = new MultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], 4326);

    // Act & Assert
    expect(function () use ($multiPolygon): void {
        TestExtendedPlace::factory()->create(['multi_polygon' => $multiPolygon]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when storing a record with extended MultiPolygon instead of the regular one', function (): void {
    // Arrange
    EloquentSpatial::useMultiPolygon(MultiPolygon::class);
    $multiPolygon = new ExtendedMultiPolygon([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
    ], 4326);

    // Act & Assert
    expect(function () use ($multiPolygon): void {
        TestPlace::factory()->create(['multi_polygon' => $multiPolygon]);
    })->toThrow(InvalidArgumentException::class);
});
