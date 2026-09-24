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
