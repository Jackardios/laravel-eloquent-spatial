<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use PDO;
use WeakMap;

class AxisOrder
{
    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $supported = null;

    public static function supported(ConnectionInterface $connection): bool
    {
        if (! ($connection instanceof MySqlConnection)) {
            return false;
        }

        if ($connection instanceof MariaDbConnection) {
            return false;
        }

        // The SQL is executed on the write connection, so its server decides. Keyed by the PDO,
        // so that a reconnect, possibly to another server, is detected again.
        $pdo = $connection->getPdo();

        if (self::$supported === null) {
            /** @var WeakMap<PDO, bool> $supported */
            $supported = new WeakMap;
            self::$supported = $supported;
        }

        $supported = self::$supported;

        if (! isset($supported[$pdo])) {
            $supported[$pdo] = self::isMySql8OrAbove($pdo);
        }

        return $supported[$pdo] === true;
    }

    private static function isMySql8OrAbove(PDO $pdo): bool
    {
        $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        return is_string($version)
            && ! str_contains($version, 'MariaDB')
            && version_compare($version, '8.0.0', '>=');
    }
}
