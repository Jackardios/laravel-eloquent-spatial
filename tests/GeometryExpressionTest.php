<?php

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Jackardios\EloquentSpatial\GeometryExpression;

it('casts the expression to geometry on PostgreSQL only', function (string $connectionClass, string $expected): void {
    /** @var class-string<MySqlConnection|PostgresConnection|SQLiteConnection> $connectionClass */
    $connection = new $connectionClass(static function (): never {
        throw new RuntimeException('The PDO must not be resolved.');
    });

    expect((new GeometryExpression('"test_places"."point"'))->normalize($connection))->toBe($expected);
})->with([
    'PostgreSQL' => [PostgresConnection::class, '"test_places"."point"::geometry'],
    'MySQL' => [MySqlConnection::class, '"test_places"."point"'],
    'SQLite' => [SQLiteConnection::class, '"test_places"."point"'],
]);
