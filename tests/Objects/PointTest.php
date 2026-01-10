<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPoint;

it('creates a model record with point', function (): void {
    $point = new Point(180, 0);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point]);

    expect($testPlace->point)->toBeInstanceOf(Point::class);
    expect($testPlace->point)->toEqual($point);
});

it('creates a model record with point with SRID integer', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point]);

    expect($testPlace->point->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with point with SRID enum', function (): void {
    $point = new Point(180, 0, Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point]);

    expect($testPlace->point->srid)->toBe(Srid::WGS84->value);
});

it('creates point with default 0 SRID from JSON', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $point = new Point(180, 0);

    // Act
    $pointFromJson = Point::fromJson('{"type":"Point","coordinates":[180,0]}');

    // Assert
    expect($pointFromJson)->toEqual($point);
    expect($pointFromJson->srid)->toBe(0);
});

it('creates point with default 4326 SRID from JSON', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $point = new Point(180, 0);

    // Act
    $pointFromJson = Point::fromJson('{"type":"Point","coordinates":[180,0]}');

    // Assert
    expect($pointFromJson)->toEqual($point);
    expect($pointFromJson->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates point with SRID from JSON', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $pointFromJson = Point::fromJson('{"type":"Point","coordinates":[180,0]}', Srid::WGS84->value);

    expect($pointFromJson)->toEqual($point);
});

it('creates point with default 0 SRID from array', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $point = new Point(180, 0);

    // Act
    $pointFromJson = Point::fromArray(['type' => 'Point', 'coordinates' => [180, 0]]);

    // Assert
    expect($pointFromJson)->toEqual($point);
    expect($pointFromJson->srid)->toBe(0);
});

it('creates point with default 4326 SRID from array', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $point = new Point(180, 0);

    // Act
    $pointFromJson = Point::fromArray(['type' => 'Point', 'coordinates' => [180, 0]]);

    // Assert
    expect($pointFromJson)->toEqual($point);
    expect($pointFromJson->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates point with SRID from array', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $pointFromJson = Point::fromArray(['type' => 'Point', 'coordinates' => [180, 0]], Srid::WGS84->value);

    expect($pointFromJson)->toEqual($point);
});

it('generates point JSON', function (): void {
    $point = new Point(180, 0);

    $json = $point->toJson();

    $expectedJson = '{"type":"Point","coordinates":[180,0]}';
    expect($json)->toBe($expectedJson);
});

it('throws exception when creating point from invalid JSON', function (): void {
    expect(function (): void {
        Point::fromJson('{"type":"Point","coordinates":[]}');
    })->toThrow(InvalidArgumentException::class);
});

it('creates point with default 0 SRID from WKT', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $point = new Point(180, 0);

    $pointFromWkt = Point::fromWkt('POINT(180 0)');

    expect($pointFromWkt)->toEqual($point);
    expect($pointFromWkt->srid)->toBe(0);
});

it('creates point with default 4326 SRID from WKT', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $point = new Point(180, 0);

    // Act
    $pointFromWkt = Point::fromWkt('POINT(180 0)');

    // Assert
    expect($pointFromWkt)->toEqual($point);
    expect($pointFromWkt->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates point with SRID from WKT', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $pointFromWkt = Point::fromWkt('POINT(180 0)', Srid::WGS84->value);

    expect($pointFromWkt)->toEqual($point);
});

it('generates point WKT', function (): void {
    $point = new Point(180, 0);

    $wkt = $point->toWkt();

    $expectedWkt = 'POINT(180 0)';
    expect($wkt)->toBe($expectedWkt);
});

it('creates point from WKB', function (): void {
    $point = new Point(180, 0);

    $pointFromWkb = Point::fromWkb($point->toWkb());

    expect($pointFromWkb)->toEqual($point);
});

it('creates point with SRID from WKB', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $pointFromWkb = Point::fromWkb($point->toWkb());

    expect($pointFromWkb)->toEqual($point);
});

it('casts a Point to a string', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    expect($point->__toString())->toEqual('POINT(180 0)');
});

it('adds a macro toPoint', function (): void {
    Geometry::macro('getName', function (): string {
        /** @var Geometry $this */
        return class_basename($this);
    });

    $point = new Point(180, 0, Srid::WGS84->value);

    // @phpstan-ignore-next-line
    expect($point->getName())->toBe('Point');
});

it('uses an extended Point class', function (): void {
    // Arrange
    EloquentSpatial::usePoint(ExtendedPoint::class);
    $point = new ExtendedPoint(180, 0, 4326);

    // Act
    /** @var TestExtendedPlace $testPlace */
    $testPlace = TestExtendedPlace::factory()->create(['point' => $point])->fresh();

    // Assert
    expect($testPlace->point)->toBeInstanceOf(ExtendedPoint::class);
    expect($testPlace->point)->toEqual($point);
});

it('throws exception when storing a record with regular Point instead of the extended one', function (): void {
    // Arrange
    EloquentSpatial::usePoint(ExtendedPoint::class);
    $point = new Point(180, 0, 4326);

    // Act & Assert
    expect(function () use ($point): void {
        TestExtendedPlace::factory()->create(['point' => $point]);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when storing a record with extended Point instead of the regular one', function (): void {
    // Arrange
    EloquentSpatial::usePoint(Point::class);
    $point = new ExtendedPoint(180, 0, 4326);

    // Act & Assert
    expect(function () use ($point): void {
        TestPlace::factory()->create(['point' => $point]);
    })->toThrow(InvalidArgumentException::class);
});

// Edge case tests for boundary coordinates

it('creates point at maximum longitude boundary', function (): void {
    $point = new Point(180.0, 0.0);

    expect($point->longitude)->toBe(180.0);
    expect($point->latitude)->toBe(0.0);
});

it('creates point at minimum longitude boundary', function (): void {
    $point = new Point(-180.0, 0.0);

    expect($point->longitude)->toBe(-180.0);
    expect($point->latitude)->toBe(0.0);
});

it('creates point at maximum latitude boundary', function (): void {
    $point = new Point(0.0, 90.0);

    expect($point->longitude)->toBe(0.0);
    expect($point->latitude)->toBe(90.0);
});

it('creates point at minimum latitude boundary', function (): void {
    $point = new Point(0.0, -90.0);

    expect($point->longitude)->toBe(0.0);
    expect($point->latitude)->toBe(-90.0);
});

it('creates point at zero coordinates', function (): void {
    $point = new Point(0.0, 0.0);

    expect($point->longitude)->toBe(0.0);
    expect($point->latitude)->toBe(0.0);
});

it('creates point with high precision coordinates', function (): void {
    $longitude = 123.45678901234;
    $latitude = -12.34567890123;
    $point = new Point($longitude, $latitude);

    expect($point->longitude)->toBe($longitude);
    expect($point->latitude)->toBe($latitude);
});

it('preserves SRID through WKB roundtrip', function (): void {
    $point = new Point(100.0, 50.0, Srid::WGS84->value);
    $wkb = $point->toWkb();
    $restored = Point::fromWkb($wkb);

    expect($restored->srid)->toBe(Srid::WGS84->value);
    expect($restored->longitude)->toBe(100.0);
    expect($restored->latitude)->toBe(50.0);
});

it('preserves high precision coordinates through WKB roundtrip', function (): void {
    $longitude = 123.45678901234;
    $latitude = -12.34567890123;
    $point = new Point($longitude, $latitude);
    $wkb = $point->toWkb();
    $restored = Point::fromWkb($wkb);

    expect($restored->longitude)->toBe($longitude);
    expect($restored->latitude)->toBe($latitude);
});

// Coordinate validation tests

it('throws exception for latitude above maximum', function (): void {
    expect(function (): void {
        new Point(0.0, 90.1);
    })->toThrow(InvalidArgumentException::class, 'Latitude must be between -90 and 90');
});

it('throws exception for latitude below minimum', function (): void {
    expect(function (): void {
        new Point(0.0, -90.1);
    })->toThrow(InvalidArgumentException::class, 'Latitude must be between -90 and 90');
});

it('throws exception for longitude above maximum', function (): void {
    expect(function (): void {
        new Point(180.1, 0.0);
    })->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180');
});

it('throws exception for longitude below minimum', function (): void {
    expect(function (): void {
        new Point(-180.1, 0.0);
    })->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180');
});

it('allows boundary coordinates', function (): void {
    $point1 = new Point(180.0, 90.0);
    $point2 = new Point(-180.0, -90.0);

    expect($point1->longitude)->toBe(180.0);
    expect($point1->latitude)->toBe(90.0);
    expect($point2->longitude)->toBe(-180.0);
    expect($point2->latitude)->toBe(-90.0);
});
