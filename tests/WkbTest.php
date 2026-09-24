<?php

use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedGeometryCollection;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPolygon;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPolygon;

/**
 * @return array<string, array{0: Closure(): Geometry, 1: string}>
 */
function wkbFixtures(): array
{
    // The expected bytes are what version 4 wrote with geoPHP.
    return [
        'Point' => [
            fn () => new Point(1.5, -2.25, 4326),
            'e61000000101000000000000000000f83f00000000000002c0',
        ],
        'Point with SRID 0' => [
            fn () => new Point(-180, 90),
            '00000000010100000000000000008066c00000000000805640',
        ],
        'Point with SRID 3857' => [
            fn () => new Point(-20037508.34, 20037508.34, 3857),
            '110f00000101000000d7a37045f81b73c1d7a37045f81b7341',
        ],
        'LineString' => [
            fn () => new LineString([new Point(0, 0), new Point(1.1, -1.1), new Point(2, 2)], 4326),
            'e6100000010200000003000000000000000000000000000000000000009a9999999999f13f9a9999999999f1bf00000000000000400000000000000040',
        ],
        'Polygon with a hole' => [
            fn () => new Polygon([
                new LineString([new Point(0, 0), new Point(10, 0), new Point(10, 10), new Point(0, 10), new Point(0, 0)]),
                new LineString([new Point(2, 2), new Point(3, 2), new Point(3, 3), new Point(2, 2)]),
            ], 4326),
            'e61000000103000000020000000500000000000000000000000000000000000000000000000000244000000000000000000000000000002440000000000000244000000000000000000000000000002440000000000000000000000000000000000400000000000000000000400000000000000040000000000000084000000000000000400000000000000840000000000000084000000000000000400000000000000040',
        ],
        'MultiPoint' => [
            fn () => new MultiPoint([new Point(1, 2), new Point(3, 4)], 4326),
            'e61000000104000000020000000101000000000000000000f03f0000000000000040010100000000000000000008400000000000001040',
        ],
        'MultiLineString' => [
            fn () => new MultiLineString([
                new LineString([new Point(1, 2), new Point(3, 4)]),
                new LineString([new Point(5, 6), new Point(7, 8)]),
            ], 4326),
            'e6100000010500000002000000010200000002000000000000000000f03f000000000000004000000000000008400000000000001040010200000002000000000000000000144000000000000018400000000000001c400000000000002040',
        ],
        'MultiPolygon' => [
            fn () => new MultiPolygon([
                new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])]),
                new Polygon([new LineString([new Point(5, 5), new Point(6, 5), new Point(6, 6), new Point(5, 5)])]),
            ], 4326),
            'e61000000106000000020000000103000000010000000400000000000000000000000000000000000000000000000000f03f0000000000000000000000000000f03f000000000000f03f000000000000000000000000000000000103000000010000000400000000000000000014400000000000001440000000000000184000000000000014400000000000001840000000000000184000000000000014400000000000001440',
        ],
        'GeometryCollection' => [
            fn () => new GeometryCollection([
                new Point(1, 2),
                new LineString([new Point(1, 2), new Point(3, 4)]),
                new GeometryCollection([new Point(5, 6)]),
            ], 4326),
            'e61000000107000000030000000101000000000000000000f03f0000000000000040010200000002000000000000000000f03f000000000000004000000000000008400000000000001040010700000001000000010100000000000000000014400000000000001840',
        ],
    ];
}

it('writes the same WKB as version 4', function (Geometry $geometry, string $expectedHex): void {
    expect(bin2hex($geometry->toWkb()))->toBe($expectedHex);
})->with(wkbFixtures());

it('writes WKB that the database reads back unchanged', function (Geometry $geometry): void {
    $wkb = bin2hex(substr($geometry->toWkb(), 4));

    $sql = DB::connection()->getDriverName() === 'pgsql'
        ? "SELECT encode(ST_AsBinary(ST_GeomFromWKB(decode(?, 'hex')), 'NDR'), 'hex') AS wkb"
        : 'SELECT LOWER(HEX(ST_AsBinary(ST_GeomFromWKB(UNHEX(?))))) AS wkb';

    expect(DB::scalar($sql, [$wkb]))->toBe($wkb);
})->with(fn () => array_map(fn (array $fixture): array => [$fixture[0]], wkbFixtures()));

it('writes an empty geometry collection', function (): void {
    expect(bin2hex((new GeometryCollection([], 4326))->toWkb()))->toBe('e6100000'.'01'.'07000000'.'00000000');
});

it('writes the SRID as a little-endian integer', function (): void {
    expect(substr((new Point(0, 0, 0x01020304))->toWkb(), 0, 4))->toBe("\x04\x03\x02\x01");
});

it('writes extended geometry classes like their base classes', function (Geometry $extended, Geometry $base): void {
    expect(bin2hex($extended->toWkb()))->toBe(bin2hex($base->toWkb()));
})->with([
    'Point' => [new ExtendedPoint(1, 2, 4326), new Point(1, 2, 4326)],
    'LineString' => [new ExtendedLineString([new Point(1, 2), new Point(3, 4)]), new LineString([new Point(1, 2), new Point(3, 4)])],
    'Polygon' => [
        new ExtendedPolygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])]),
        new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])]),
    ],
    'MultiPoint' => [new ExtendedMultiPoint([new Point(1, 2)]), new MultiPoint([new Point(1, 2)])],
    'MultiLineString' => [
        new ExtendedMultiLineString([new LineString([new Point(1, 2), new Point(3, 4)])]),
        new MultiLineString([new LineString([new Point(1, 2), new Point(3, 4)])]),
    ],
    'MultiPolygon' => [
        new ExtendedMultiPolygon([new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])])]),
        new MultiPolygon([new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])])]),
    ],
    'GeometryCollection' => [new ExtendedGeometryCollection([new Point(1, 2)]), new GeometryCollection([new Point(1, 2)])],
]);

it('rejects a geometry class that it cannot write', function (): void {
    $geometry = new class extends Geometry
    {
        public function __construct()
        {
            $this->srid = 0;
        }

        public function toWkt(): string
        {
            return 'POINT(0 0)';
        }

        public function getWktData(): string
        {
            return '0 0';
        }

        public function getCoordinates(): array
        {
            return [0, 0];
        }
    };

    expect(fn () => $geometry->toWkb())->toThrow(InvalidArgumentException::class, 'Cannot write '.$geometry::class.' as WKB.');
});
