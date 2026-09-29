<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\MySqlConnection;
use PDO;

class AxisOrder
{
    public static function supported(ConnectionInterface $connection): bool
    {
        // A MariaDbConnection (Laravel 11+) is recognised without connecting.
        if (! ($connection instanceof MySqlConnection) || $connection->isMaria()) {
            return false;
        }

        // The SQL is executed on the write connection, so its server decides. PDO keeps the version from the
        // handshake, so reading it sends no query.
        $version = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return is_string($version) && version_compare($version, '8.0.0', '>=');
    }
}
