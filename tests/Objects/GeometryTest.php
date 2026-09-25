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
    $testPlace = TestPlace::factory()->create(['geometry' => $point])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(Point::class);
    expect($testPlace->geometry)->toEqual($point);
});

it('creates a model record with geometry (line string)', function (): void {
    // Arrange
    $lineString = LineString::fromJson('{"type":"LineString","coordinates":[[180,0],[179,1]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $lineString])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(LineString::class);
    expect($testPlace->geometry)->toEqual($lineString);
});

it('creates a model record with geometry (multi point)', function (): void {
    // Arrange
    $multiPoint = MultiPoint::fromJson('{"type":"MultiPoint","coordinates":[[180,0],[179,1]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiPoint])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiPoint::class);
    expect($testPlace->geometry)->toEqual($multiPoint);
});

it('creates a model record with geometry (multi line string)', function (): void {
    // Arrange
    $multiLineString = MultiLineString::fromJson('{"type":"MultiLineString","coordinates":[[[180,0],[179,1]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiLineString])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiLineString::class);
    expect($testPlace->geometry)->toEqual($multiLineString);
});

it('creates a model record with geometry (polygon)', function (): void {
    // Arrange
    $polygon = Polygon::fromJson('{"type":"Polygon","coordinates":[[[180,0],[179,1],[180,1],[180,0]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $polygon])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(Polygon::class);
    expect($testPlace->geometry)->toEqual($polygon);
});

it('creates a model record with geometry (multi polygon)', function (): void {
    // Arrange
    $multiPolygon = MultiPolygon::fromJson('{"type":"MultiPolygon","coordinates":[[[[180,0],[179,1],[180,1],[180,0]]]]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $multiPolygon])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(MultiPolygon::class);
    expect($testPlace->geometry)->toEqual($multiPolygon);
});

it('creates a model record with geometry (geometry collection)', function (): void {
    // Arrange
    $geometryCollection = GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[180,0]}]}');

    // Act
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $geometryCollection])->fresh();

    // Assert
    expect($testPlace->geometry)->toBeInstanceOf(GeometryCollection::class);
    expect($testPlace->geometry)->toEqual($geometryCollection);
});

// Edge case tests for WKB/WKT parsing

it('throws without a warning when WKB is too short to contain an SRID', function (string $wkb): void {
    $warnings = 0;
    set_error_handler(static function () use (&$warnings): bool {
        $warnings++;

        return true;
    });

    try {
        expect(fn () => Geometry::fromWkb($wkb))
            ->toThrow(InvalidArgumentException::class, 'Invalid spatial value: the WKB is too short.');
    } finally {
        restore_error_handler();
    }

    // A warning would reach a Laravel app as an ErrorException instead.
    expect($warnings)->toBe(0);
})->with([
    'empty' => [''],
    'two bytes' => ["\x00\x20"],
]);

it('throws when WKB has an SRID but no geometry', function (): void {
    expect(fn () => Geometry::fromWkb('GGGG'))
        ->toThrow(InvalidArgumentException::class, 'Invalid spatial value');
});

it('parses hex-encoded WKB and EWKB', function (string $wkb, int $srid): void {
    expect(Point::fromWkb($wkb))->toEqual(new Point(1, 2, $srid));
})->with([
    'WKB' => ['0101000000000000000000F03F0000000000000040', 0],
    'EWKB with SRID' => ['0101000020E6100000000000000000F03F0000000000000040', 4326],
]);

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

it('does not write WKT with other characters into SQL', function (string $wkt): void {
    $point = new class($wkt) extends Point
    {
        public function __construct(private readonly string $wkt)
        {
            parent::__construct(0, 0);
        }

        public function toWkt(): string
        {
            return $this->wkt;
        }
    };

    expect(fn () => $point->toSqlExpression(DB::connection()))
        ->toThrow(InvalidArgumentException::class, 'Invalid WKT from '.$point::class.'::toWkt(): '.$wkt);
})->with([
    'a quote' => ["POINT(0 0)', 0) OR 1=1 --"],
    'a backslash' => ['POINT(0 0)\\'],
    'a semicolon' => ['POINT(0 0);'],
]);

it('writes WKT with every character that geometries use into SQL', function (): void {
    $point = new Point(-1.0E-7, 2.5E+20, 3857);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => $point])->fresh();

    expect($point->toWkt())->toBe('POINT(-1.0E-7 2.5E+20)')
        ->and($testPlace->point)->toEqual($point);
});
