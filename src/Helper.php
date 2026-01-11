<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use InvalidArgumentException;
use Jackardios\EloquentSpatial\Enums\Srid;

class Helper
{
    public static function getSrid(Srid|int|null $srid = null): int
    {
        if ($srid instanceof Srid) {
            return $srid->value;
        }

        if (is_int($srid)) {
            return $srid;
        }

        return EloquentSpatial::$defaultSrid;
    }

    /**
     * Parse ST_GeomFromText SQL expression and extract WKT and SRID.
     *
     * @return array{wkt: string, srid: int}
     *
     * @throws InvalidArgumentException
     */
    public static function parseStGeomFromText(string $expressionValue): array
    {
        $result = preg_match(
            "/ST_GeomFromText\(\s*'([^']+)'\s*(?:,\s*(\d+))?\s*(?:,\s*'([^']+)')?\s*\)/",
            $expressionValue,
            $matches
        );

        // @codeCoverageIgnoreStart
        if ($result !== 1) {
            throw new InvalidArgumentException('Unable to parse ST_GeomFromText expression: '.$expressionValue);
        }
        // @codeCoverageIgnoreEnd

        return [
            'wkt' => $matches[1],
            'srid' => (int) ($matches[2] ?? 0),
        ];
    }
}
