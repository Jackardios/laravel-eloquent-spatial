<?php

use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\AxisOrder;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\GeometryExpression;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;

it('throws exception when generating geometry from other geometry WKB', function (): void {
    expect(function (): void {
        $pointWkb = (new Point(180, 0))->toWkb();

        LineString::fromWkb($pointWkb);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when creating point with invalid latitude', function (): void {
    expect(function (): void {
        new Point(0, 91, Srid::WGS84->value);
    })->toThrow(InvalidArgumentException::class, 'Latitude must be between -90 and 90');
});

it('throws exception when creating point with invalid longitude', function (): void {
    expect(function (): void {
        new Point(181, 0, Srid::WGS84->value);
    })->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180');
});

it('throws exception when generating geometry from other geometry WKT', function (): void {
    expect(function (): void {
        $pointWkt = 'POINT(180 0)';

        LineString::fromWkt($pointWkt);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when generating geometry from non-JSON', function (): void {
    expect(function (): void {
        Point::fromJson('invalid-value');
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when generating geometry from empty JSON', function (): void {
    expect(function (): void {
        Point::fromJson('{}');
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when generating geometry from other geometry JSON', function (): void {
    expect(function (): void {
        $pointJson = '{"type":"Point","coordinates":[180,0]}';

        LineString::fromJson($pointJson);
    })->toThrow(InvalidArgumentException::class);
});

it('creates an SQL expression from a geometry', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $expression = $point->toSqlExpression(DB::connection());

    $grammar = DB::getQueryGrammar();
    $expressionValue = $expression->getValue($grammar);
    expect($expressionValue)->toEqual("ST_GeomFromText('POINT(180 0)', 4326, 'axis-order=long-lat')");
})->skip(fn () => ! AxisOrder::supported(DB::connection()));

it('creates an SQL expression from a geometry - without axis-order', function (): void {
    $point = new Point(180, 0, Srid::WGS84->value);

    $expression = $point->toSqlExpression(DB::connection());

    $grammar = DB::getQueryGrammar();
    $expressionValue = $expression->getValue($grammar);
    expect($expressionValue)->toEqual(
        (new GeometryExpression("ST_GeomFromText('POINT(180 0)', 4326)"))->normalize(DB::connection())
    );
})->skip(fn () => AxisOrder::supported(DB::connection()));

it('creates a geometry object from a geo json array', function (): void {
    $point = new Point(180, 0);
    $pointGeoJsonArray = $point->toArray();

    $geometryCollectionFromArray = Point::fromArray($pointGeoJsonArray);

    expect($geometryCollectionFromArray)->toEqual($point);
});

it('throws exception when creating a geometry object from an invalid geo json array', function (): void {
    $invalidPointGeoJsonArray = [
        'type' => 'InvalidGeometryType',
        'coordinates' => [180, 0],
    ];

    expect(function () use ($invalidPointGeoJsonArray): void {
        Geometry::fromArray($invalidPointGeoJsonArray);
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when creating a geometry object from another geometry geo json array', function (): void {
    $pointGeoJsonArray = [
        'type' => 'Point',
        'coordinates' => [180, 0],
    ];

    expect(function () use ($pointGeoJsonArray): void {
        LineString::fromArray($pointGeoJsonArray);
    })->toThrow(InvalidArgumentException::class);
});

it('creates a model record with geometry (point)', function (): void {
    // Arrange
    $point = Point::fromJson('{"type":"Point","coordinates":[180,0]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $point]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(Point::class);
    expect($testPlace->geometry)->toEqual($point);
});

it('creates a model record with geometry (line string)', function (): void {
    // Arrange
    $lineString = LineString::fromJson('{"type":"LineString","coordinates":[[180,0],[179,1]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $lineString]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(LineString::class);
    expect($testPlace->geometry)->toEqual($lineString);
});

it('creates a model record with geometry (multi point)', function (): void {
    // Arrange
    $multiPoint = MultiPoint::fromJson('{"type":"MultiPoint","coordinates":[[180,0],[179,1]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiPoint]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiPoint::class);
    expect($testPlace->geometry)->toEqual($multiPoint);
});

it('creates a model record with geometry (multi line string)', function (): void {
    // Arrange
    $multiLineString = MultiLineString::fromJson('{"type":"MultiLineString","coordinates":[[[180,0],[179,1]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiLineString]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiLineString::class);
    expect($testPlace->geometry)->toEqual($multiLineString);
});

it('creates a model record with geometry (polygon)', function (): void {
    // Arrange
    $polygon = Polygon::fromJson('{"type":"Polygon","coordinates":[[[180,0],[179,1],[180,1],[180,0]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $polygon]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(Polygon::class);
    expect($testPlace->geometry)->toEqual($polygon);
});

it('creates a model record with geometry (multi polygon)', function (): void {
    // Arrange
    $multiPolygon = MultiPolygon::fromJson('{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[180,1],[180,0]]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiPolygon]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiPolygon::class);
    expect($testPlace->geometry)->toEqual($multiPolygon);
});

it('creates a model record with geometry (geometry collection)', function (): void {
    // Arrange
    $geometryCollection = GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[180,0]}]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $geometryCollection]);

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(GeometryCollection::class);
    expect($testPlace->geometry)->toEqual($geometryCollection);
});

// Edge case tests for WKB/WKT parsing

it('throws exception when parsing invalid WKB with truncated data', function (): void {
    // Invalid WKB: only 2 bytes instead of a complete geometry
    $invalidWkb = "\x00\x20";

    expect(function () use ($invalidWkb): void {
        Geometry::fromWkb($invalidWkb);
    })->toThrow(Exception::class);
});

it('throws exception when parsing empty WKB', function (): void {
    expect(function (): void {
        Geometry::fromWkb('');
    })->toThrow(Exception::class);
});

it('throws exception when parsing invalid hex WKB', function (): void {
    expect(function (): void {
        Geometry::fromWkb('GGGG');
    })->toThrow(Exception::class);
});

it('throws exception when parsing malformed WKT', function (): void {
    expect(function (): void {
        Point::fromWkt('POINT(abc def)');
    })->toThrow(InvalidArgumentException::class);
});

it('throws exception when parsing incomplete WKT', function (): void {
    expect(function (): void {
        Point::fromWkt('POINT(');
    })->toThrow(InvalidArgumentException::class);
});
