<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedGeometryCollection;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPolygon;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPolygon;

/**
 * @return array<string, array{0: Closure(): Geometry, 1: string, 2: string, 3: Closure(): mixed}>
 */
function extendedGeometries(): array
{
    $ring = fn (): LineString => new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)]);

    // The geometry, the type, the attribute of TestPlace that has a cast of the base class, and the registration.
    return [
        'Point' => [fn () => new ExtendedPoint(1, 2), 'Point', 'point', fn () => EloquentSpatial::usePoint(ExtendedPoint::class)],
        'LineString' => [
            fn () => new ExtendedLineString([new Point(1, 2), new Point(3, 4)]),
            'LineString', 'line_string', fn () => EloquentSpatial::useLineString(ExtendedLineString::class),
        ],
        'Polygon' => [fn () => new ExtendedPolygon([$ring()]), 'Polygon', 'polygon', fn () => EloquentSpatial::usePolygon(ExtendedPolygon::class)],
        'MultiPoint' => [
            fn () => new ExtendedMultiPoint([new Point(1, 2)]),
            'MultiPoint', 'multi_point', fn () => EloquentSpatial::useMultiPoint(ExtendedMultiPoint::class),
        ],
        'MultiLineString' => [
            fn () => new ExtendedMultiLineString([new LineString([new Point(1, 2), new Point(3, 4)])]),
            'MultiLineString', 'multi_line_string', fn () => EloquentSpatial::useMultiLineString(ExtendedMultiLineString::class),
        ],
        'MultiPolygon' => [
            fn () => new ExtendedMultiPolygon([new Polygon([$ring()])]),
            'MultiPolygon', 'multi_polygon', fn () => EloquentSpatial::useMultiPolygon(ExtendedMultiPolygon::class),
        ],
        'GeometryCollection' => [
            fn () => new ExtendedGeometryCollection([new Point(1, 2), new LineString([new Point(1, 2), new Point(3, 4)])]),
            'GeometryCollection', 'geometry_collection', fn () => EloquentSpatial::useGeometryCollection(ExtendedGeometryCollection::class),
        ],
    ];
}

it('writes the GeoJSON type of the base class', function (Geometry $geometry, string $type, string $attribute, Closure $register): void {
    expect($geometry->toArray()['type'])->toBe($type);
})->with(extendedGeometries());

it('writes the GeoJSON of an extended class like the base class', function (Geometry $geometry, string $type, string $attribute, Closure $register): void {
    $extended = $geometry;
    $base = Geometry::fromWkt($extended->toWkt());

    expect($extended->toJson())->toBe($base->toJson())
        ->and($extended->toFeatureCollectionJson())->toBe($base->toFeatureCollectionJson());
})->with(extendedGeometries());

it('reads the GeoJSON of an extended class back', function (Geometry $geometry, string $type, string $attribute, Closure $register): void {
    $register();
    $extended = $geometry;

    expect(Geometry::fromJson($extended->toJson()))->toEqual($extended);
})->with(extendedGeometries());

it('writes the geometries of an extended geometry collection', function (): void {
    $collection = new ExtendedGeometryCollection([new Point(1, 2)]);

    expect($collection->toArray())->toEqual(['type' => 'GeometryCollection', 'geometries' => collect([['type' => 'Point', 'coordinates' => [1.0, 2.0]]])])
        ->and($collection->toFeatureCollectionJson())->toBe((new GeometryCollection([new Point(1, 2)]))->toFeatureCollectionJson());
});

it('saves an extended class that it read through a cast of the base class', function (Geometry $geometry, string $type, string $attribute, Closure $register): void {
    $register();
    $extended = $geometry;

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([$attribute => $extended])->fresh();
    /** @var Geometry $read */
    $read = $testPlace->getAttribute($attribute);

    $testPlace->setAttribute($attribute, $read);
    $testPlace->setAttribute($attribute, $read::fromArray($read->toArray(), $read->srid));
    $testPlace->save();

    expect($read)->toBeInstanceOf($extended::class)
        ->and($testPlace->fresh()?->getAttribute($attribute))->toEqual($extended);
})->with(extendedGeometries());

it('does not store a polygon in a cast of multi line strings', function (): void {
    $polygon = new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])]);

    expect(fn () => TestPlace::factory()->create(['multi_line_string' => $polygon]))
        ->toThrow(InvalidArgumentException::class, 'Expected '.MultiLineString::class.', '.Polygon::class.' given.');
});

it('does not store a line string in a cast of multi points', function (): void {
    expect(fn () => TestPlace::factory()->create(['multi_point' => new LineString([new Point(0, 0), new Point(1, 1)])]))
        ->toThrow(InvalidArgumentException::class, 'Expected '.MultiPoint::class.', '.LineString::class.' given.');
});

it('stores any geometry in a cast of the Geometry class', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => new ExtendedGeometryCollection([new Point(1, 2)])])->fresh();

    expect($testPlace->geometry)->toEqual(new GeometryCollection([new Point(1, 2)]));
});
