<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPolygon;

it('creates a model record with polygon', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['polygon' => $polygon]);

    expect($testPlace->polygon)->toBeInstanceOf(Polygon::class);
    expect($testPlace->polygon)->toEqual($polygon);
});

it('creates a model record with polygon with SRID integer', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['polygon' => $polygon]);

    expect($testPlace->polygon->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with polygon with SRID enum', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['polygon' => $polygon]);

    expect($testPlace->polygon->srid)->toBe(Srid::WGS84->value);
});

it('creates polygon from JSON', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $polygonFromJson = Polygon::fromJson('{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}');

    expect($polygonFromJson)->toEqual($polygon);
});

it('creates polygon with SRID from JSON', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84->value);

    $polygonFromJson = Polygon::fromJson('{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}', Srid::WGS84->value);

    expect($polygonFromJson)->toEqual($polygon);
});

it('creates polygon from array', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $polygonFromJson = Polygon::fromArray(['type' => 'Polygon', 'coordinates' => [[[180, 0], [179, 1], [178, 2], [177, 3], [180, 0]]]]);

    expect($polygonFromJson)->toEqual($polygon);
});

it('creates polygon with SRID from array', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84->value);

    $polygonFromJson = Polygon::fromArray(['type' => 'Polygon', 'coordinates' => [[[180, 0], [179, 1], [178, 2], [177, 3], [180, 0]]]], Srid::WGS84->value);

    expect($polygonFromJson)->toEqual($polygon);
});

it('generates polygon JSON', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $json = $polygon->toJson();

    $expectedJson = '{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}';
    expect($json)->toBe($expectedJson);
});

it('generates polygon feature collection JSON', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $featureCollectionJson = $polygon->toFeatureCollectionJson();

    $expectedFeatureCollectionJson = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}}]}';
    expect($featureCollectionJson)->toBe($expectedFeatureCollectionJson);
});

it('creates polygon from WKT', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $polygonFromWkt = Polygon::fromWkt('POLYGON((180 0, 179 1, 178 2, 177 3, 180 0))');

    expect($polygonFromWkt)->toEqual($polygon);
});

it('creates polygon with SRID from WKT', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84->value);

    $polygonFromWkt = Polygon::fromWkt('POLYGON((180 0, 179 1, 178 2, 177 3, 180 0))', Srid::WGS84->value);

    expect($polygonFromWkt)->toEqual($polygon);
});

it('generates polygon WKT', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $wkt = $polygon->toWkt();

    $expectedWkt = 'POLYGON((180 0, 179 1, 178 2, 177 3, 180 0))';
    expect($wkt)->toBe($expectedWkt);
});

it('creates polygon from WKB', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    $polygonFromWkb = Polygon::fromWkb($polygon->toWkb());

    expect($polygonFromWkb)->toEqual($polygon);
});

it('creates polygon with SRID from WKB', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], Srid::WGS84->value);

    $polygonFromWkb = Polygon::fromWkb($polygon->toWkb());

    expect($polygonFromWkb)->toEqual($polygon);
});

it('throws exception when polygon has no line strings', function (): void {
    expect(function (): void {
        new Polygon([]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when creating polygon from incorrect geometry', function (): void {
    expect(function (): void {
        // @phpstan-ignore-next-line
        new Polygon([
            new Point(0, 0),
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('casts a Polygon to a string', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    expect($polygon->__toString())->toEqual('POLYGON((180 0, 179 1, 178 2, 177 3, 180 0))');
});

it('adds a macro toPolygon', function (): void {
    Geometry::macro('getName', function (): string {
        /** @var Geometry $this */
        return class_basename($this);
    });

    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    // @phpstan-ignore-next-line
    expect($polygon->getName())->toBe('Polygon');
});

it('uses an extended Polygon class', function (): void {
    // Arrange
    EloquentSpatial::usePolygon(ExtendedPolygon::class);
    $polygon = new ExtendedPolygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], 4326);

    // Act
    /** @var TestExtendedPlace $testPlace */
    $testPlace = TestExtendedPlace::factory()->create(['polygon' => $polygon])->fresh();

    // Assert
    expect($testPlace->polygon)->toBeInstanceOf(ExtendedPolygon::class);
    expect($testPlace->polygon)->toEqual($polygon);
});

it('throws exception when storing a record with regular Polygon instead of the extended one', function (): void {
    // Arrange
    EloquentSpatial::usePolygon(ExtendedPolygon::class);
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], 4326);

    // Act & Assert
    expect(function () use ($polygon): void {
        TestExtendedPlace::factory()->create(['polygon' => $polygon]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when storing a record with extended Polygon instead of the regular one', function (): void {
    // Arrange
    EloquentSpatial::usePolygon(Polygon::class);
    $polygon = new ExtendedPolygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ], 4326);

    // Act & Assert
    expect(function () use ($polygon): void {
        TestPlace::factory()->create(['polygon' => $polygon]);
    })->toThrow(InvalidArgumentException::class);
});
