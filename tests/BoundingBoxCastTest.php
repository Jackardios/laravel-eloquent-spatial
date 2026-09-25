<?php

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\BoundingBoxCast;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;

function castedBoundingBox(): BoundingBox
{
    return new BoundingBox(new Point(-30.5, -12.25), new Point(91.5, 40.75));
}

it('stores a bounding box as a polygon in the geometry format', function (BoundingBoxCast $cast): void {
    $model = new TestPlace;

    $stored = $cast->set($model, 'bounding_box', castedBoundingBox(), []);

    $expected = castedBoundingBox()->toGeometry()->toSqlExpression($model->getConnection());
    expect($stored)->toBeInstanceOf(ExpressionContract::class)
        ->and($stored)->toEqual($expected);
})->with([
    'default format' => fn () => new BoundingBoxCast,
    'explicit format' => fn () => new BoundingBoxCast(BoundingBoxCast::FORMAT_GEOMETRY),
    'castUsing without arguments' => fn () => BoundingBox::castUsing([]),
]);

it('stores a bounding box as a JSON string in the json format', function (BoundingBoxCast $cast): void {
    $stored = $cast->set(new TestPlace, 'bounding_box_json', castedBoundingBox(), []);

    expect($stored)->toBe('{"left":-30.5,"bottom":-12.25,"right":91.5,"top":40.75}');
})->with([
    'explicit format' => fn () => new BoundingBoxCast(BoundingBoxCast::FORMAT_JSON),
    'castUsing with json' => fn () => BoundingBox::castUsing(['json']),
]);

it('throws for an unknown format', function (): void {
    expect(fn () => new BoundingBoxCast('wkt'))
        ->toThrow(InvalidArgumentException::class, 'Invalid format "wkt". Supported formats: geometry, json');
});

it('passes a raw expression through unchanged', function (string $format): void {
    $expression = DB::raw("ST_GeomFromText('POLYGON((0 0,1 0,1 1,0 1,0 0))')");

    expect((new BoundingBoxCast($format))->set(new TestPlace, 'bounding_box', $expression, []))->toBe($expression);
})->with([BoundingBoxCast::FORMAT_GEOMETRY, BoundingBoxCast::FORMAT_JSON]);

it('reads a bounding box assigned as a raw expression', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => DB::raw("ST_GeomFromText('POLYGON((-30.5 -12.25,91.5 -12.25,91.5 40.75,-30.5 40.75,-30.5 -12.25))')"),
    ]);

    expect($testPlace->getOriginal('bounding_box'))->toEqual(castedBoundingBox())
        ->and($testPlace->fresh()?->bounding_box)->toEqual(castedBoundingBox());
});

it('throws when the geometry column holds neither a polygon nor a multi polygon', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create();
    DB::table('test_places')->where('id', $testPlace->id)->update([
        'bounding_box' => (new Point(1, 2))->toSqlExpression(DB::connection()),
    ]);

    expect(fn () => $testPlace->fresh()?->bounding_box)
        ->toThrow(InvalidArgumentException::class, 'Expected Polygon or MultiPolygon, '.Point::class.' given.');
});

it('throws when the json column holds invalid JSON', function (): void {
    $cast = new BoundingBoxCast(BoundingBoxCast::FORMAT_JSON);

    $exception = null;

    try {
        $cast->get(new TestPlace, 'bounding_box_json', '{"left":', []);
    } catch (InvalidArgumentException $exception) {
    }

    expect($exception?->getMessage())->toStartWith('Invalid JSON for BoundingBox: ')
        ->and($exception?->getPrevious())->toBeInstanceOf(JsonException::class);
});

it('throws when the json column holds a non-string value', function (): void {
    $cast = new BoundingBoxCast(BoundingBoxCast::FORMAT_JSON);

    expect(fn () => $cast->get(new TestPlace, 'bounding_box_json', DB::raw('{}'), []))
        ->toThrow(InvalidArgumentException::class, 'JSON format expects string value from database');
});

it('reads null and empty values as null', function (string $format, ?string $value): void {
    expect((new BoundingBoxCast($format))->get(new TestPlace, 'bounding_box', $value, []))->toBeNull();
})->with([
    'geometry null' => [BoundingBoxCast::FORMAT_GEOMETRY, null],
    'geometry empty' => [BoundingBoxCast::FORMAT_GEOMETRY, ''],
    'json null' => [BoundingBoxCast::FORMAT_JSON, null],
    'json empty' => [BoundingBoxCast::FORMAT_JSON, ''],
]);

it('reads back the bounding box that was stored', function (BoundingBox $bbox, string $attribute): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([$attribute => $bbox])->fresh();

    expect($testPlace->{$attribute}?->toArray())->toBe($bbox->toArray());
})->with([
    'narrow' => [fn () => BoundingBox::fromArray(['left' => -30.5, 'bottom' => -12.25, 'right' => 91.5, 'top' => 40.75])],
    'wider than 180 degrees' => [fn () => BoundingBox::fromArray(['left' => -170.0, 'bottom' => -10.0, 'right' => 170.0, 'top' => 10.0])],
    'the whole world' => [fn () => BoundingBox::fromArray(['left' => -180.0, 'bottom' => -90.0, 'right' => 180.0, 'top' => 90.0])],
    'across the antimeridian' => [fn () => BoundingBox::fromArray(['left' => 170.0, 'bottom' => -10.0, 'right' => -170.0, 'top' => 10.0])],
    'across the antimeridian and wider than 180 degrees' => [fn () => BoundingBox::fromArray(['left' => 10.0, 'bottom' => -10.0, 'right' => -10.0, 'top' => 10.0])],
    'from the antimeridian' => [fn () => BoundingBox::fromArray(['left' => 180.0, 'bottom' => -10.0, 'right' => -170.0, 'top' => 10.0])],
    'to the antimeridian' => [fn () => BoundingBox::fromArray(['left' => 170.0, 'bottom' => -10.0, 'right' => -180.0, 'top' => 10.0])],
])->with(['bounding_box', 'bounding_box_json']);

it('reads the envelope of a polygon that it did not write', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => DB::raw("ST_GeomFromText('POLYGON((-170 0,170 -10,0 20,-170 0))')"),
    ]);

    expect($testPlace->fresh()?->bounding_box?->toArray())
        ->toBe(['left' => -170.0, 'bottom' => -10.0, 'right' => 170.0, 'top' => 20.0]);
});

it('reads a multi polygon that it did not write as the smallest box around it', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'bounding_box' => DB::raw("ST_GeomFromText('MULTIPOLYGON(((170 0,175 0,175 5,170 0)),((-175 0,-170 0,-170 5,-175 0)))')"),
    ]);

    expect($testPlace->fresh()?->bounding_box?->toArray())
        ->toBe(['left' => 170.0, 'bottom' => 0.0, 'right' => -170.0, 'top' => 5.0]);
});

it('stores the geometry format with the SRID of the cast', function (BoundingBox $bbox): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['bounding_box_4326' => $bbox]);

    $srid = DB::scalar('SELECT ST_SRID(bounding_box_4326) FROM test_places WHERE id = ?', [$testPlace->id]);

    expect($srid)->toEqual(4326)
        ->and($testPlace->fresh()?->bounding_box_4326?->toArray())->toBe($bbox->toArray());
})->with([
    'narrow' => [fn () => castedBoundingBox()],
    'across the antimeridian' => [fn () => BoundingBox::fromArray(['left' => 170, 'bottom' => -10, 'right' => -170, 'top' => 10])],
]);

it('takes the SRID from the cast arguments', function (): void {
    $stored = BoundingBox::castUsing(['geometry', '4326'])->set(new TestPlace, 'bounding_box_4326', castedBoundingBox(), []);

    expect($stored)->toEqual(castedBoundingBox()->toGeometry(4326)->toSqlExpression((new TestPlace)->getConnection()));
});

it('rejects an invalid SRID argument', function (string $format, string $srid, string $message): void {
    expect(fn () => BoundingBox::castUsing([$format, $srid]))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'not a number' => ['geometry', 'wgs84', 'Invalid SRID "wgs84".'],
    'negative' => ['geometry', '-1', 'Invalid SRID "-1".'],
    'with the json format' => ['json', '4326', 'An SRID can be given only for the geometry format.'],
]);
