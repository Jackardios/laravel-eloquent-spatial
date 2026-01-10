<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedLineString;

it('creates a model record with line string', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['line_string' => $lineString]);

    expect($testPlace->line_string)->toBeInstanceOf(LineString::class);
    expect($testPlace->line_string)->toEqual($lineString);
});

it('creates a model record with line string with SRID integer', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['line_string' => $lineString]);

    expect($testPlace->line_string->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with line string with SRID enum', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['line_string' => $lineString]);

    expect($testPlace->line_string->srid)->toBe(Srid::WGS84->value);
});

it('creates line string from JSON', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $lineStringFromJson = LineString::fromJson('{"type":"LineString","coordinates":[[180,0],[179,1]]}');

    expect($lineStringFromJson)->toEqual($lineString);
});

it('creates line string with SRID from JSON', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84->value);

    $lineStringFromJson = LineString::fromJson('{"type":"LineString","coordinates":[[180,0],[179,1]]}', Srid::WGS84->value);

    expect($lineStringFromJson)->toEqual($lineString);
});

it('creates line string from array', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $lineStringFromJson = LineString::fromArray(['type' => 'LineString', 'coordinates' => [[180, 0], [179, 1]]]);

    expect($lineStringFromJson)->toEqual($lineString);
});

it('creates line string with SRID from array', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84->value);

    $lineStringFromJson = LineString::fromArray(['type' => 'LineString', 'coordinates' => [[180, 0], [179, 1]]], Srid::WGS84->value);

    expect($lineStringFromJson)->toEqual($lineString);
});

it('generates line string JSON', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $json = $lineString->toJson();

    $expectedJson = '{"type":"LineString","coordinates":[[180,0],[179,1]]}';
    expect($json)->toBe($expectedJson);
});

it('generates line string feature collection JSON', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $featureCollectionJson = $lineString->toFeatureCollectionJson();

    $expectedFeatureCollectionJson = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"LineString","coordinates":[[180,0],[179,1]]}}]}';
    expect($featureCollectionJson)->toBe($expectedFeatureCollectionJson);
});

it('creates line string from WKT', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $lineStringFromWkt = LineString::fromWkt('LINESTRING(180 0, 179 1)');

    expect($lineStringFromWkt)->toEqual($lineString);
});

it('creates line string with SRID from WKT', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84->value);

    $lineStringFromWkt = LineString::fromWkt('LINESTRING(180 0, 179 1)', Srid::WGS84->value);

    expect($lineStringFromWkt)->toEqual($lineString);
});

it('generates line string WKT', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $wkt = $lineString->toWkt();

    $expectedWkt = 'LINESTRING(180 0, 179 1)';
    expect($wkt)->toBe($expectedWkt);
});

it('creates line string from WKB', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $lineStringFromWkb = LineString::fromWkb($lineString->toWkb());

    expect($lineStringFromWkb)->toEqual($lineString);
});

it('creates line string with SRID from WKB', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], Srid::WGS84->value);

    $lineStringFromWkb = LineString::fromWkb($lineString->toWkb());

    expect($lineStringFromWkb)->toEqual($lineString);
});

it('throws exception when line string has less than two points', function (): void {
    expect(function (): void {
        new LineString([
            new Point(180, 0),
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when creating line string from incorrect geometry', function (): void {
    expect(function (): void {
        // @phpstan-ignore-next-line
        new LineString([
            Polygon::fromJson('{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}'),
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('casts a LineString to a string', function (): void {
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    expect($lineString->__toString())->toEqual('LINESTRING(180 0, 179 1)');
});

it('adds a macro toLineString', function (): void {
    Geometry::macro('getName', function (): string {
        /** @var Geometry $this */
        return class_basename($this);
    });

    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    // @phpstan-ignore-next-line
    expect($lineString->getName())->toBe('LineString');
});

it('uses an extended LineString class', function (): void {
    // Arrange
    EloquentSpatial::useLineString(ExtendedLineString::class);
    $lineString = new ExtendedLineString([
        new Point(180, 0),
        new Point(179, 1),
    ], 4326);

    // Act
    /** @var TestExtendedPlace $testPlace */
    $testPlace = TestExtendedPlace::factory()->create(['line_string' => $lineString])->fresh();

    // Assert
    expect($testPlace->line_string)->toBeInstanceOf(ExtendedLineString::class);
    expect($testPlace->line_string)->toEqual($lineString);
});

it('throws exception when storing a record with regular LineString instead of the extended one', function (): void {
    // Arrange
    EloquentSpatial::useLineString(ExtendedLineString::class);
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ], 4326);

    // Act & Assert
    expect(function () use ($lineString): void {
        TestExtendedPlace::factory()->create(['line_string' => $lineString]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when storing a record with extended LineString instead of the regular one', function (): void {
    // Arrange
    EloquentSpatial::useLineString(LineString::class);
    $lineString = new ExtendedLineString([
        new Point(180, 0),
        new Point(179, 1),
    ], 4326);

    // Act & Assert
    expect(function () use ($lineString): void {
        TestPlace::factory()->create(['line_string' => $lineString]);
    })->toThrow(InvalidArgumentException::class);
});
