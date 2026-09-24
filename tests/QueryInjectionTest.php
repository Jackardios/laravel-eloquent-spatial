<?php

use Illuminate\Database\QueryException;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;

$injectedOperators = [
    'always true' => ['< 0 OR 1=1 OR 0 <'],
    'not a comparison' => ['like'],
    'empty' => [''],
];

$injectedDirections = [
    'limit' => ['asc LIMIT 0'],
    'subquery' => ['asc, (select 1)'],
    'unknown' => ['up'],
];

it('rejects a distance operator that is not a comparison', function (string $scope, string $operator): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    expect(fn () => TestPlace::query()->{$scope}('point', new Point(0, 0), $operator, 1)->get())
        ->toThrow(InvalidArgumentException::class, 'Invalid comparison operator');
})->with(['whereDistance', 'whereDistanceSphere'])->with($injectedOperators);

it('rejects an SRID operator that is not a comparison', function (string $operator): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    expect(fn () => TestPlace::query()->whereSrid('point', $operator, 0)->get())
        ->toThrow(InvalidArgumentException::class, 'Invalid comparison operator');
})->with($injectedOperators);

it('accepts every comparison operator', function (string $operator, int $expectedCount): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    expect(TestPlace::query()->whereDistance('point', new Point(0, 0), $operator, 0)->count())->toBe($expectedCount)
        ->and(TestPlace::query()->whereSrid('point', $operator, 0)->count())->toBe($expectedCount);
})->with([
    ['=', 1],
    ['<', 0],
    ['>', 0],
    ['<=', 1],
    ['>=', 1],
    ['<>', 0],
    ['!=', 0],
]);

it('rejects an order direction other than asc and desc', function (string $scope, string $direction): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    expect(fn () => TestPlace::query()->{$scope}('point', new Point(0, 0), $direction)->get())
        ->toThrow(InvalidArgumentException::class, 'Order direction must be "asc" or "desc"');
})->with(['orderByDistance', 'orderByDistanceSphere'])->with($injectedDirections);

it('accepts the order direction in any case', function (string $scope, string $direction): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    expect(TestPlace::query()->{$scope}('point', new Point(0, 0), $direction)->get())->toHaveCount(1);
})->with(['orderByDistance', 'orderByDistanceSphere'])->with(['asc', 'DESC', 'Asc']);

it('quotes the alias', function (string $scope, array $arguments): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);
    $alias = 'dist`an"ce';

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::query()->{$scope}(...$arguments, ...[$alias])->firstOrFail();

    expect($testPlace->getAttributes())->toHaveKey($alias);
})->with([
    'withDistance' => ['withDistance', ['point', new Point(0, 0)]],
    'withDistanceSphere' => ['withDistanceSphere', ['point', new Point(0, 0)]],
    'withCentroid' => ['withCentroid', ['point']],
]);

it('does not let the alias add a column', function (string $scope): void {
    TestPlace::factory()->create(['point' => new Point(0, 0)]);

    // Laravel's grammar reads " as " in an identifier as an alias, so the query fails instead of selecting "leaked".
    expect(fn () => TestPlace::query()->{$scope}('point', new Point(0, 0), 'distance, 42 AS leaked')->get())
        ->toThrow(QueryException::class);
})->with(['withDistance', 'withDistanceSphere']);
