<?php

use Illuminate\Database\DatabaseServiceProvider;
use Jackardios\EloquentSpatial\EloquentSpatialServiceProvider;

it('does not register the database services a second time', function (): void {
    $db = app('db');

    app()->register(EloquentSpatialServiceProvider::class, force: true);

    expect(app('db'))->toBe($db)
        ->and(app()->getProviders(DatabaseServiceProvider::class))->toHaveCount(1);
});
