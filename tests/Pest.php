<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Jackardios\EloquentSpatial\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function databaseServerVersion(): string
{
    $version = DB::connection()->getDriverName() === 'pgsql' ? null : DB::scalar('SELECT VERSION()');

    return is_string($version) ? $version : '';
}

/**
 * Detected from the server rather than the driver: Laravel 10 connects to MariaDB with the mysql driver.
 */
function isMariaDb(): bool
{
    return str_contains(databaseServerVersion(), 'MariaDB');
}

/**
 * Whether the server is MySQL 8.0+, which applies the SRID's axis order.
 *
 * Detected independently of the package code under test.
 */
function isMySql8OrAbove(): bool
{
    $version = databaseServerVersion();

    return DB::connection()->getDriverName() === 'mysql'
        && ! str_contains($version, 'MariaDB')
        && version_compare($version, '8.0.0', '>=');
}
