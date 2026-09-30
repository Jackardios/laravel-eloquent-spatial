<?php

use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Jackardios\EloquentSpatial\AxisOrder;

function pdoReportingVersion(string $version): PDO
{
    return new class($version) extends PDO
    {
        // The parent constructor is skipped on purpose: no connection is opened.
        public function __construct(private readonly string $version) {}

        public function getAttribute(int $attribute): mixed
        {
            return $attribute === PDO::ATTR_SERVER_VERSION ? $this->version : throw new LogicException('Unexpected attribute.');
        }
    };
}

function unreachablePdo(): Closure
{
    return static function (): never {
        throw new RuntimeException('The PDO must not be resolved.');
    };
}

it('detects axis order support from the server version', function (string $version, bool $supported): void {
    $connection = new MySqlConnection(pdoReportingVersion($version));

    expect(AxisOrder::supported($connection))->toBe($supported);
})->with([
    'MySQL 8.0' => ['8.0.36', true],
    'MySQL 8.4' => ['8.4.2', true],
    'MySQL 5.7' => ['5.7.44', false],
    'MariaDB' => ['10.11.6-MariaDB-1:10.11.6+maria~ubu2204', false],
    'MariaDB behind a 5.5.5 prefix' => ['5.5.5-10.11.6-MariaDB', false],
]);

it('reads the server version from the write connection', function (): void {
    $connection = new MySqlConnection(pdoReportingVersion('8.0.36'));
    $connection->setReadPdo(unreachablePdo());

    expect(AxisOrder::supported($connection))->toBeTrue();
});

it('does not support axis order on PostgreSQL', function (): void {
    expect(AxisOrder::supported(new PostgresConnection(unreachablePdo())))->toBeFalse();
});

it('does not connect to detect a MariaDB connection', function (): void {
    expect(AxisOrder::supported(new MariaDbConnection(unreachablePdo())))->toBeFalse();
});
