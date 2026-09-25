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
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

    expect($testPlace->point)->toBeInstanceOf(Point::class);
    expect($testPlace->point)->toEqual($point);
});

it('creates a model record with point with SRID integer', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

    expect($testPlace->point->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with point with SRID enum', function (): void {
    $point = new Point(180, 0, Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

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

it('rejects coordinates that are not finite', function (float $longitude, float $latitude, int $srid): void {
    expect(fn () => new Point($longitude, $latitude, $srid))
        ->toThrow(InvalidArgumentException::class, 'Coordinates must be finite numbers');
})->with([
    'NaN longitude' => [NAN, 0.0],
    'NaN latitude' => [0.0, NAN],
    'infinite longitude' => [INF, 0.0],
    'negative infinite latitude' => [0.0, -INF],
])->with(['SRID 0' => [0], 'SRID 4326' => [4326], 'SRID 3857' => [3857]]);

it('validates the longitude and latitude ranges of SRID 0 and 4326', function (int|Srid $srid): void {
    expect(fn () => new Point(180.1, 0.0, $srid))->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180')
        ->and(fn () => new Point(0.0, 90.1, $srid))->toThrow(InvalidArgumentException::class, 'Latitude must be between -90 and 90');
})->with(['SRID 0' => [0], 'SRID 4326' => [4326], 'Srid::WGS84' => [Srid::WGS84]]);

it('does not validate the ranges of other SRIDs', function (int|Srid $srid): void {
    $point = new Point(-20037508.34, 20037508.34, $srid);

    expect($point->longitude)->toBe(-20037508.34)
        ->and($point->latitude)->toBe(20037508.34);
})->with(['SRID 3857' => [3857], 'Srid::WEB_MERCATOR' => [Srid::WEB_MERCATOR], 'SRID 32633' => [32633]]);

it('validates the ranges of the default SRID', function (): void {
    EloquentSpatial::setDefaultSrid(Srid::WEB_MERCATOR);
    $projected = new Point(-20037508.34, 20037508.34);

    EloquentSpatial::setDefaultSrid(Srid::WGS84);

    expect($projected->srid)->toBe(3857)
        ->and(fn () => new Point(180.1, 0.0))->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180');
});

it('stores and reads a point outside the longitude and latitude ranges in a projected SRID', function (): void {
    $point = new Point(-20037508.34, 20037508.34, Srid::WEB_MERCATOR);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

    expect($testPlace->point)->toEqual($point);
});

it('reads a point outside the longitude and latitude ranges in a projected SRID', function (Closure $read): void {
    expect($read())->toEqual(new Point(-20037508.34, 20037508.34, Srid::WEB_MERCATOR));
})->with([
    'WKT' => fn () => Point::fromWkt('POINT(-20037508.34 20037508.34)', Srid::WEB_MERCATOR),
    'GeoJSON' => fn () => Point::fromJson('{"type":"Point","coordinates":[-20037508.34,20037508.34]}', Srid::WEB_MERCATOR),
    'WKB' => fn () => Point::fromWkb((new Point(-20037508.34, 20037508.34, Srid::WEB_MERCATOR))->toWkb()),
]);

it('validates the ranges of points read in SRID 4326', function (Closure $read): void {
    expect($read)->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180');
})->with([
    'WKT' => fn () => Point::fromWkt('POINT(-20037508.34 0)', Srid::WGS84),
    'GeoJSON' => fn () => Point::fromJson('{"type":"Point","coordinates":[-20037508.34,0]}', Srid::WGS84),
    'WKB' => fn () => Point::fromWkb(pack('V', 4326).substr((new Point(-20037508.34, 0, Srid::WEB_MERCATOR))->toWkb(), 4)),
]);

it('writes coordinates to WKT without losing precision', function (float $longitude, float $latitude, string $expectedWkt): void {
    expect((new Point($longitude, $latitude))->toWkt())->toBe($expectedWkt);
})->with([
    'integers' => [180.0, -0.0, 'POINT(180 -0)'],
    'more than 14 digits' => [123.45678901234567, 0.1 + 0.2, 'POINT(123.45678901234567 0.30000000000000004)'],
    'close to the range' => [-179.99999999999997, 89.99999999999999, 'POINT(-179.99999999999997 89.99999999999999)'],
    'small numbers' => [1.0E-7, -2.5E-10, 'POINT(1.0E-7 -2.5E-10)'],
]);

it('stores coordinates without losing precision', function (float $longitude, float $latitude, int $srid): void {
    $point = new Point($longitude, $latitude, $srid);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

    expect($testPlace->point)->toEqual($point);
})->with([
    'more than 14 digits' => [123.45678901234567, 0.1 + 0.2],
    'close to the range' => [-179.99999999999997, 89.99999999999999],
    'small numbers' => [1.0E-7, -2.5E-10],
])->with(['SRID 0' => [0], 'SRID 4326' => [4326]]);

it('allows boundary coordinates', function (): void {
    $point1 = new Point(180.0, 90.0);
    $point2 = new Point(-180.0, -90.0);

    expect($point1->longitude)->toBe(180.0);
    expect($point1->latitude)->toBe(90.0);
    expect($point2->longitude)->toBe(-180.0);
    expect($point2->latitude)->toBe(-90.0);
});
