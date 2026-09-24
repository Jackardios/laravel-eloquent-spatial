<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Helper;

it('resolves an SRID from an enum, an integer or the default', function (): void {
    EloquentSpatial::setDefaultSrid(Srid::WGS84);

    expect(Helper::getSrid(Srid::WEB_MERCATOR))->toBe(3857)
        ->and(Helper::getSrid(0))->toBe(0)
        ->and(Helper::getSrid(2154))->toBe(2154)
        ->and(Helper::getSrid())->toBe(4326)
        ->and(Helper::getSrid(null))->toBe(4326);
});

it('parses an ST_GeomFromText expression', function (string $expression, string $wkt, int $srid): void {
    expect(Helper::parseStGeomFromText($expression))->toBe(['wkt' => $wkt, 'srid' => $srid]);
})->with([
    'without SRID' => ["ST_GeomFromText('POINT(1 2)')", 'POINT(1 2)', 0],
    'with SRID' => ["ST_GeomFromText('POINT(1 2)', 4326)", 'POINT(1 2)', 4326],
    'with axis order' => ["ST_GeomFromText('POINT(1 2)', 4326, 'axis-order=long-lat')", 'POINT(1 2)', 4326],
    'PostGIS cast' => ["ST_GeomFromText('LINESTRING(0 0,1 1)', 3857)::geometry", 'LINESTRING(0 0,1 1)', 3857],
    'extra whitespace' => ["ST_GeomFromText( 'POINT(1 2)' , 4326 , 'axis-order=long-lat' )", 'POINT(1 2)', 4326],
]);

it('throws when an expression is not ST_GeomFromText', function (): void {
    expect(fn () => Helper::parseStGeomFromText('ST_MakePoint(1, 2)'))
        ->toThrow(InvalidArgumentException::class, 'Unable to parse ST_GeomFromText expression: ST_MakePoint(1, 2)');
});
