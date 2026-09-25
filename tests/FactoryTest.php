<?php

use Brick\Geo\Io\EwkbReader;
use Brick\Geo\Io\EwkbWriter;
use Brick\Geo\Io\EwktReader;
use Brick\Geo\Io\Internal\WkbByteOrder;
use Brick\Geo\Io\WkbReader;
use Brick\Geo\Io\WkbWriter;
use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Factory;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\Point;

function littleEndianPointWkb(float $x, float $y): string
{
    return pack('CVee', 1, 1, $x, $y);
}

function bigEndianPointWkb(float $x, float $y): string
{
    return pack('CNEE', 0, 1, $x, $y);
}

function ewkbPoint(int $srid, float $x, float $y): string
{
    return pack('CVVee', 1, 0x20000001, $srid, $x, $y);
}

function nestedCollectionWkt(int $collections): string
{
    return str_repeat('GEOMETRYCOLLECTION(', $collections).'POINT(1 2)'.str_repeat(')', $collections);
}

function nestedCollectionWkb(int $collections): string
{
    return str_repeat(pack('CVV', 1, 7, 1), $collections).littleEndianPointWkb(1, 2);
}

function nestedCollectionJson(int $collections): string
{
    return str_repeat('{"type":"GeometryCollection","geometries":[', $collections)
        .'{"type":"Point","coordinates":[1,2]}'
        .str_repeat(']}', $collections);
}

// WKB

it('reads WKB in every supported encoding', function (string $wkb, Point $expected): void {
    expect(Point::fromWkb($wkb))->toEqual($expected);
})->with([
    'MySQL' => [pack('V', 4326).littleEndianPointWkb(1.5, 2.5), new Point(1.5, 2.5, 4326)],
    'MySQL with big-endian WKB' => [pack('V', 4326).bigEndianPointWkb(1.5, 2.5), new Point(1.5, 2.5, 4326)],
    'MySQL as hex' => [bin2hex(pack('V', 4326).littleEndianPointWkb(1.5, 2.5)), new Point(1.5, 2.5, 4326)],
    'WKB' => [littleEndianPointWkb(1.5, 2.5), new Point(1.5, 2.5, 0)],
    'big-endian WKB' => [bigEndianPointWkb(1.5, 2.5), new Point(1.5, 2.5, 0)],
    'EWKB' => [ewkbPoint(4326, 1.5, 2.5), new Point(1.5, 2.5, 4326)],
    'WKB as hex' => [bin2hex(littleEndianPointWkb(1.5, 2.5)), new Point(1.5, 2.5, 0)],
    'big-endian WKB as hex' => [bin2hex(bigEndianPointWkb(1.5, 2.5)), new Point(1.5, 2.5, 0)],
    'EWKB as uppercase hex' => [strtoupper(bin2hex(ewkbPoint(4326, 1.5, 2.5))), new Point(1.5, 2.5, 4326)],
]);

it('reads WKB like brick/geo', function (string $wkt, WkbByteOrder $byteOrder, string $encoding, bool $hex): void {
    $brickGeometry = (new EwktReader)->read($wkt);

    $writer = $encoding === 'EWKB' ? new EwkbWriter : new WkbWriter;
    $writer->setByteOrder($byteOrder);
    $wkb = ($encoding === 'MySQL' ? pack('V', 4326) : '').$writer->write($brickGeometry);

    // brick/geo reads the MySQL format as WKB with a separate SRID.
    $read = $encoding === 'MySQL'
        ? (new WkbReader)->read(substr($wkb, 4), 4326)
        : (new EwkbReader)->read($wkb);

    expect(Geometry::fromWkb($hex ? bin2hex($wkb) : $wkb))->toEqual(Geometry::fromWkt($read->asText(), $read->srid()));
})->with([
    'Point' => 'SRID=4326;POINT(1.5 -2.25)',
    'Point Z' => 'SRID=4326;POINT Z(1 2 3)',
    'Point M' => 'SRID=4326;POINT M(1 2 4)',
    'Point ZM' => 'SRID=4326;POINT ZM(1 2 3 4)',
    'LineString' => 'SRID=4326;LINESTRING(0 0, 1.1 -1.1, 2 2)',
    'LineString ZM' => 'SRID=4326;LINESTRING ZM(0 0 1 2, 1.1 -1.1 3 4)',
    'Polygon with a hole' => 'SRID=4326;POLYGON((0 0, 10 0, 10 10, 0 10, 0 0), (2 2, 3 2, 3 3, 2 2))',
    'Polygon Z' => 'SRID=4326;POLYGON Z((0 0 1, 1 0 2, 1 1 3, 0 0 1))',
    'MultiPoint' => 'SRID=4326;MULTIPOINT((1 2), (3 4))',
    'MultiPoint M' => 'SRID=4326;MULTIPOINT M((1 2 5), (3 4 6))',
    'MultiLineString' => 'SRID=4326;MULTILINESTRING((1 2, 3 4), (5 6, 7 8))',
    'MultiPolygon' => 'SRID=4326;MULTIPOLYGON(((0 0, 1 0, 1 1, 0 0)), ((5 5, 6 5, 6 6, 5 5)))',
    'MultiPolygon Z' => 'SRID=4326;MULTIPOLYGON Z(((0 0 1, 1 0 1, 1 1 1, 0 0 1)))',
    'GeometryCollection' => 'SRID=4326;GEOMETRYCOLLECTION(POINT(1 2), LINESTRING(1 2, 3 4), GEOMETRYCOLLECTION(POINT(5 6)))',
    'empty GeometryCollection' => 'SRID=4326;GEOMETRYCOLLECTION EMPTY',
])->with([
    'little-endian' => [WkbByteOrder::LittleEndian],
    'big-endian' => [WkbByteOrder::BigEndian],
])->with([
    'WKB' => ['WKB'],
    'EWKB' => ['EWKB'],
    'MySQL' => ['MySQL'],
])->with([
    'binary' => [false],
    'hex' => [true],
]);

it('reads WKB whose geometries have different byte orders', function (): void {
    $wkb = pack('CVV', 1, 7, 2).bigEndianPointWkb(1, 2).pack('CNN', 0, 4, 1).littleEndianPointWkb(3, 4);

    expect(GeometryCollection::fromWkb($wkb))
        ->toEqual(new GeometryCollection([new Point(1, 2), new MultiPoint([new Point(3, 4)])]));
});

it('does not read another format when the values are invalid', function (): void {
    expect(fn () => Point::fromWkb(pack('V', 4326).littleEndianPointWkb(200, 0)))
        ->toThrow(InvalidArgumentException::class, 'Longitude must be between -180 and 180, got: 200');
});

it('drops Z and M coordinates from WKB', function (string $wkb): void {
    expect(Point::fromWkb($wkb))->toEqual(new Point(1.5, 2.5, 0));
})->with([
    'ISO Z' => [pack('CVeee', 1, 1001, 1.5, 2.5, 3)],
    'ISO M' => [pack('CVeee', 1, 2001, 1.5, 2.5, 3)],
    'ISO ZM' => [pack('CVeeee', 1, 3001, 1.5, 2.5, 3, 4)],
    'EWKB Z' => [pack('CVeee', 1, 0x80000001, 1.5, 2.5, 3)],
    'EWKB ZM' => [pack('CVeeee', 1, 0xC0000001, 1.5, 2.5, 3, 4)],
]);

it('rejects invalid WKB', function (string $wkb, string $message): void {
    expect(fn () => Geometry::fromWkb($wkb))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'trailing data' => [pack('V', 4326).littleEndianPointWkb(1, 2)."\x00", 'Invalid spatial value: unexpected data after the WKB geometry.'],
    'truncated' => [substr(pack('V', 4326).littleEndianPointWkb(1, 2), 0, 15), 'Invalid spatial value: unexpected end of the WKB.'],
    'a huge count' => [pack('V', 0).pack('CVV', 1, 2, 0xFFFFFFFF), 'Invalid spatial value: unexpected end of the WKB.'],
    'an invalid byte order' => [pack('V', 0).pack('CVee', 2, 1, 1, 2), 'Invalid spatial value: invalid WKB byte order 2.'],
    'hex of odd length' => ['0101000', 'Invalid spatial value: the hex WKB has an odd length.'],
    'a Triangle' => [pack('V', 0).pack('CVVV', 1, 17, 1, 4).pack('e*', 0, 0, 1, 0, 0, 1, 0, 0), 'Invalid spatial value: unsupported WKB geometry type 17.'],
    'a CircularString' => [pack('V', 0).pack('CVV', 1, 8, 3).pack('e*', 0, 0, 1, 1, 2, 0), 'Invalid spatial value: unsupported WKB geometry type 8.'],
    'a LineString in a MultiPoint' => [pack('V', 0).pack('CVVCVV', 1, 4, 1, 1, 2, 2).pack('e*', 0, 0, 1, 1), 'Invalid spatial value: expected Point in the WKB, got LineString.'],
    'a Polygon in a MultiLineString' => [pack('V', 0).pack('CVVCVVV', 1, 5, 1, 1, 3, 1, 4).pack('e*', 0, 0, 1, 0, 1, 1, 0, 0), 'Invalid spatial value: expected LineString in the WKB, got Polygon.'],
    // PostGIS writes an empty point as NaN coordinates.
    'an empty point' => [pack('V', 0).pack('CVee', 1, 1, NAN, NAN), 'Invalid spatial value: empty points are not supported.'],
]);

// WKT

it('reads the SRID of EWKT unless an SRID is given', function (): void {
    EloquentSpatial::setDefaultSrid(Srid::WEB_MERCATOR);

    expect(Point::fromWkt('SRID=4326;POINT(1 2)'))->toEqual(new Point(1, 2, 4326))
        ->and(Point::fromWkt('srid=0;POINT(1 2)'))->toEqual(new Point(1, 2, 0))
        ->and(Point::fromWkt('SRID=4326;POINT(1 2)', 0))->toEqual(new Point(1, 2, 0))
        ->and(Point::fromWkt('POINT(1 2)'))->toEqual(new Point(1, 2, 3857))
        ->and(LineString::fromWkt('SRID=4326;LINESTRING(1 2, 3 4)')[1])->toEqual(new Point(3, 4, 4326));
});

it('reads WKT regardless of case and whitespace', function (string $wkt): void {
    expect(Point::fromWkt($wkt))->toEqual(new Point(1.5, 2.5));
})->with(['point(1.5 2.5)', "  POINT ( 1.5\t2.5 )\n"]);

it('drops Z and M coordinates from WKT', function (string $wkt): void {
    expect(Point::fromWkt($wkt))->toEqual(new Point(1.5, 2.5));
})->with(['POINT Z (1.5 2.5 3)', 'POINT M (1.5 2.5 3)', 'POINT ZM (1.5 2.5 3 4)']);

it('rejects values that are not WKT', function (string $wkt): void {
    expect(fn () => Geometry::fromWkt($wkt))->toThrow(InvalidArgumentException::class, 'Invalid spatial value');
})->with([
    'GeoJSON' => ['{"type":"Point","coordinates":[1,2]}'],
    'hex WKB' => ['0101000000000000000000F03F0000000000000040'],
    'trailing data' => ['POINT(1 2) x'],
    'three coordinates without Z' => ['POINT(1 2 3)'],
    'an empty point' => ['POINT EMPTY'],
    'a Triangle' => ['TRIANGLE((0 0, 1 0, 0 1, 0 0))'],
    'a CircularString' => ['CIRCULARSTRING(0 0, 1 1, 2 0)'],
]);

// GeoJSON

it('uses the given or the default SRID for GeoJSON', function (): void {
    $json = '{"type":"Point","coordinates":[1,2],"crs":{"type":"name","properties":{"name":"EPSG:3857"}}}';

    expect(Point::fromJson($json))->toEqual(new Point(1, 2, 0))
        ->and(Point::fromJson($json, Srid::WEB_MERCATOR))->toEqual(new Point(1, 2, 3857));

    EloquentSpatial::setDefaultSrid(Srid::WGS84);

    expect(MultiPoint::fromJson('{"type":"MultiPoint","coordinates":[[1,2]]}')[0])->toEqual(new Point(1, 2, 4326));
});

it('reads a GeoJSON Feature and FeatureCollection', function (string $json, Geometry $expected): void {
    expect(Geometry::fromJson($json))->toEqual($expected);
})->with([
    'Feature' => ['{"type":"Feature","properties":{"a":1},"geometry":{"type":"Point","coordinates":[1,2]}}', new Point(1, 2)],
    'Feature without properties' => ['{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2]}}', new Point(1, 2)],
    'Feature with empty properties as written by version 4' => ['{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[1,2]}}', new Point(1, 2)],
    'FeatureCollection of one' => ['{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[1,2]}}]}', new Point(1, 2)],
    'FeatureCollection of two' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[1,2]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[3,4]}}]}',
        new GeometryCollection([new Point(1, 2), new Point(3, 4)]),
    ],
    'wrong case' => ['{"type":"POINT","coordinates":[1,2]}', new Point(1, 2)],
    'Z coordinate' => ['{"type":"Point","coordinates":[1,2,3]}', new Point(1, 2)],
]);

it('reads the properties literally only where they are properties', function (): void {
    $json = '{"type":"Feature","properties":{"note":"\"properties\":[]"},"geometry":{"type":"Point","coordinates":[1,2]}}';

    expect(Geometry::fromJson($json))->toEqual(new Point(1, 2));
});

it('rejects invalid GeoJSON', function (string $json, string $message): void {
    expect(fn () => Geometry::fromJson($json))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'coordinates as strings' => ['{"type":"Point","coordinates":["1","2"]}', 'Invalid spatial value'],
    'a Feature without geometry' => ['{"type":"Feature","properties":{},"geometry":null}', 'Invalid spatial value: a GeoJSON Feature has no geometry.'],
    'an empty FeatureCollection' => ['{"type":"FeatureCollection","features":[]}', 'Invalid spatial value: the GeoJSON FeatureCollection has no features.'],
    'WKT' => ['POINT(1 2)', 'Invalid spatial value'],
    'invalid JSON' => ['{"type":', 'Invalid spatial value'],
    'an empty point' => ['{"type":"Point","coordinates":[]}', 'Invalid spatial value'],
]);

// Factory::parse

it('detects the format', function (string $value, Point $expected): void {
    EloquentSpatial::setDefaultSrid(Srid::WEB_MERCATOR);

    expect(Factory::parse($value))->toEqual($expected);
})->with([
    'WKT' => ['POINT(1 2)', new Point(1, 2, 0)],
    'EWKT' => ['SRID=4326;POINT(1 2)', new Point(1, 2, 4326)],
    'GeoJSON' => ['  {"type":"Point","coordinates":[1,2]}', new Point(1, 2, 0)],
    'WKB' => [littleEndianPointWkb(1, 2), new Point(1, 2, 0)],
    'EWKB as hex' => [bin2hex(ewkbPoint(4326, 1, 2)), new Point(1, 2, 4326)],
    'hex that starts with a letter' => [bin2hex(pack('V', 4326).littleEndianPointWkb(1, 2)), new Point(1, 2, 4326)],
    'MySQL' => [pack('V', 4326).littleEndianPointWkb(1, 2), new Point(1, 2, 4326)],
    // The SRID comes first, and its first byte can be a letter or a brace.
    'MySQL with SRID 2154' => [pack('V', 2154).littleEndianPointWkb(1, 2), new Point(1, 2, 2154)],
    'MySQL with SRID 3395' => [pack('V', 3395).littleEndianPointWkb(1, 2), new Point(1, 2, 3395)],
    'MySQL with SRID 32635' => [pack('V', 32635).littleEndianPointWkb(1, 2), new Point(1, 2, 32635)],
]);

it('does not read other formats', function (string $value): void {
    expect(fn () => Factory::parse($value))->toThrow(InvalidArgumentException::class, 'Invalid spatial value');
})->with([
    'KML' => ['<Point><coordinates>1,2</coordinates></Point>'],
    'geohash' => ['u4pruydqqvj'],
    'empty' => [''],
    'text' => ['hello world'],
]);

// Nesting

it('reads geometries nested up to the maximum depth', function (Closure $read): void {
    /** @var Geometry $geometry */
    $geometry = $read(63);

    for ($depth = 1; $depth < 64; $depth++) {
        if (! $geometry instanceof GeometryCollection) {
            throw new LogicException("Expected a GeometryCollection at depth {$depth}.");
        }

        $geometry = $geometry[0];
    }

    expect($geometry)->toEqual(new Point(1, 2));
})->with([
    'WKT' => fn (int $collections): Geometry => Geometry::fromWkt(nestedCollectionWkt($collections)),
    'WKB' => fn (int $collections): Geometry => Geometry::fromWkb(nestedCollectionWkb($collections)),
    'GeoJSON' => fn (int $collections): Geometry => Geometry::fromJson(nestedCollectionJson($collections)),
]);

it('reads an empty collection at the maximum depth', function (string $value): void {
    expect(Factory::parse($value))->toBeInstanceOf(GeometryCollection::class);
})->with([
    'WKT' => fn (): string => str_repeat('GEOMETRYCOLLECTION(', 63).'GEOMETRYCOLLECTION EMPTY'.str_repeat(')', 63),
    'WKB' => fn (): string => str_repeat(pack('CVV', 1, 7, 1), 63).pack('CVV', 1, 7, 0),
    'GeoJSON' => fn (): string => str_repeat('{"type":"GeometryCollection","geometries":[', 63)
        .'{"type":"GeometryCollection","geometries":[]}'
        .str_repeat(']}', 63),
]);

it('counts the geometries of a multi geometry as one level deeper', function (string $value): void {
    expect(fn () => Factory::parse($value))
        ->toThrow(InvalidArgumentException::class, 'geometries nested deeper than 64 levels are not supported');
})->with([
    'WKT' => fn (): string => str_repeat('GEOMETRYCOLLECTION(', 63).'MULTIPOINT((1 2))'.str_repeat(')', 63),
    'WKB' => fn (): string => pack('V', 0).str_repeat(pack('CVV', 1, 7, 1), 63).pack('CVV', 1, 4, 1).littleEndianPointWkb(1, 2),
    'GeoJSON' => fn (): string => str_repeat('{"type":"GeometryCollection","geometries":[', 63)
        .'{"type":"MultiPoint","coordinates":[[1,2]]}'
        .str_repeat(']}', 63),
]);

it('rejects geometries nested deeper than the maximum depth', function (Closure $read, int $collections): void {
    expect(fn () => $read($collections))->toThrow(InvalidArgumentException::class, 'Invalid spatial value');
})->with([
    'WKT' => fn (int $collections): Geometry => Geometry::fromWkt(nestedCollectionWkt($collections)),
    'WKB' => fn (int $collections): Geometry => Geometry::fromWkb(nestedCollectionWkb($collections)),
    'GeoJSON' => fn (int $collections): Geometry => Geometry::fromJson(nestedCollectionJson($collections)),
])->with([
    'one level too deep' => [64],
    // Deep enough to crash PHP 8.4+ if it were read.
    'far too deep' => [20000],
]);
