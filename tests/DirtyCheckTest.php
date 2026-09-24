<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;

/**
 * @return list<string>
 */
function updateQueriesOf(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();
    } finally {
        DB::disableQueryLog();
    }

    $queries = [];

    /** @var list<array{query: string}> $log */
    $log = DB::getQueryLog();

    foreach ($log as $entry) {
        if (str_starts_with(strtolower($entry['query']), 'update')) {
            $queries[] = $entry['query'];
        }
    }

    return $queries;
}

it('saves a change of only the SRID', function (): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['point' => new Point(1, 2, 4326)])->fresh();

    $testPlace->point = new Point(1, 2, 3857);

    expect($testPlace->isDirty('point'))->toBeTrue();

    $testPlace->save();

    expect($testPlace->fresh()?->point)->toEqual(new Point(1, 2, 3857));
});

it('saves a change of only the geometry type', function (Geometry $original, Geometry $changed): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry' => $original])->fresh();

    $testPlace->geometry = $changed;

    expect($testPlace->isDirty('geometry'))->toBeTrue();

    $testPlace->save();

    expect($testPlace->fresh()?->geometry)->toEqual($changed);
})->with([
    'Point to MultiPoint' => [new Point(1, 2), new MultiPoint([new Point(1, 2)])],
    'LineString to MultiPoint' => [
        new LineString([new Point(0, 0), new Point(1, 1)]),
        new MultiPoint([new Point(0, 0), new Point(1, 1)]),
    ],
]);

it('does not rewrite geometry and bounding box columns that were not changed', function (bool $read): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        'point' => new Point(1, 2),
        'bounding_box' => BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40]),
        'bounding_box_json' => BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40]),
    ])->fresh();

    if ($read) {
        // Reading fills Eloquent's cast cache, which is merged back into the attributes on save.
        $testPlace->getAttribute('point');
        $testPlace->getAttribute('bounding_box');
        $testPlace->getAttribute('bounding_box_json');
    }

    $testPlace->setAttribute('address', 'changed');

    $queries = updateQueriesOf(fn () => $testPlace->save());

    expect($queries)->toHaveCount(1);
    expect($queries[0])->toContain('address');
    expect($queries[0])->not->toContain('point');
    expect($queries[0])->not->toContain('bounding_box');
})->with([
    'not read' => [false],
    'read' => [true],
]);

it('saves a changed bounding box', function (string $attribute): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        $attribute => BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40]),
    ])->fresh();

    $testPlace->{$attribute} = BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 41]);

    expect($testPlace->isDirty($attribute))->toBeTrue();

    $testPlace->save();

    expect($testPlace->fresh()?->{$attribute}?->toArray())
        ->toEqual(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 41]);
})->with(['bounding_box', 'bounding_box_json']);

it('treats an equal bounding box as unchanged', function (string $attribute): void {
    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create([
        $attribute => BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40]),
    ])->fresh();

    $testPlace->{$attribute} = BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40]);

    expect($testPlace->isDirty($attribute))->toBeFalse();
})->with(['bounding_box', 'bounding_box_json']);

it('detects geometry changes on models without the HasSpatial trait', function (): void {
    $model = new class extends Model
    {
        protected $table = 'test_places';

        protected $guarded = [];

        protected $casts = ['point' => Point::class];
    };

    /** @var Model $place */
    $place = $model->newQuery()->create(['name' => 'name', 'address' => 'address', 'point' => new Point(1, 2, 4326)])->fresh();

    $place->setAttribute('point', new Point(1, 2, 4326));
    expect($place->isDirty('point'))->toBeFalse();

    $place->setAttribute('point', new Point(1, 2, 3857));
    expect($place->isDirty('point'))->toBeTrue();
});

it('treats an unreadable stored value as changed', function (string $attribute, mixed $value): void {
    $testPlace = new TestPlace;
    $testPlace->setRawAttributes([$attribute => 'not a spatial value'], sync: true);

    $testPlace->{$attribute} = $value;

    expect($testPlace->isDirty($attribute))->toBeTrue();
})->with([
    'geometry' => ['point', new Point(1, 2)],
    'bounding box' => ['bounding_box_json', BoundingBox::fromArray(['left' => 10, 'bottom' => 20, 'right' => 30, 'top' => 40])],
]);
