<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial\Objects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\AxisOrder;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Factory;
use Jackardios\EloquentSpatial\GeometryCast;
use Jackardios\EloquentSpatial\GeometryExpression;
use Jackardios\EloquentSpatial\Helper;
use Jackardios\EloquentSpatial\Wkb;
use JsonException;
use JsonSerializable;
use stdClass;
use Stringable;

abstract class Geometry implements Arrayable, Castable, Jsonable, JsonSerializable, Stringable
{
    use Macroable;

    public int $srid;

    abstract public function toWkt(): string;

    abstract public function getWktData(): string;

    public function __toString(): string
    {
        return $this->toWkt();
    }

    /**
     * @param  int  $options
     *
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode($this, $options | JSON_THROW_ON_ERROR);
    }

    /**
     * The WKB as MySQL stores it: the SRID as a little-endian 32-bit integer, followed by little-endian WKB.
     */
    public function toWkb(): string
    {
        return pack('V', $this->srid).Wkb::write($this);
    }

    /**
     * Reads the WKB that MySQL stores (a 4-byte SRID followed by WKB), or WKB or EWKB, binary or hex.
     *
     * The SRID is read from the MySQL format or EWKB, and is 0 otherwise.
     *
     * @throws InvalidArgumentException
     */
    public static function fromWkb(string $wkb): static
    {
        return self::expectInstance(Factory::parseWkb($wkb));
    }

    /**
     * Reads WKT or EWKT.
     *
     * @param  int|Srid|null  $srid  The SRID, or null for the SRID of the EWKT or the default SRID.
     *
     * @throws InvalidArgumentException
     */
    public static function fromWkt(string $wkt, int|Srid|null $srid = null): static
    {
        return self::expectInstance(Factory::parseWkt($wkt, $srid === null ? null : Helper::getSrid($srid), Helper::getSrid()));
    }

    /**
     * Reads a GeoJSON geometry, a Feature, or a FeatureCollection as its only geometry or as a GeometryCollection.
     *
     * @param  int|Srid|null  $srid  The SRID, or null for the default SRID.
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $geoJson, int|Srid|null $srid = null): static
    {
        return self::expectInstance(Factory::parseJson($geoJson, Helper::getSrid($srid)));
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function expectInstance(Geometry $geometry): static
    {
        if (! ($geometry instanceof static)) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', static::class, $geometry::class)
            );
        }

        return $geometry;
    }

    /**
     * @param  array<string, mixed>  $geometry
     *
     * @throws JsonException
     */
    public static function fromArray(array $geometry, int|Srid|null $srid = null): static
    {
        $geoJson = json_encode($geometry, JSON_THROW_ON_ERROR);

        return static::fromJson($geoJson, $srid);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{type: string, coordinates: array<mixed>}
     */
    public function toArray(): array
    {
        return [
            // A geometry class that is not one of the types keeps its own name.
            'type' => Helper::geometryType($this) ?? class_basename(static::class),
            'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toFeatureCollectionJson(): string
    {
        if ($this instanceof GeometryCollection && Helper::geometryType($this) === 'GeometryCollection') {
            $geometries = $this->getGeometries();
        } else {
            $geometries = collect([$this]);
        }

        $features = $geometries->map(static function (self $geometry): array {
            return [
                'type' => 'Feature',
                // An object, as GeoJSON requires, rather than the empty array [].
                'properties' => new stdClass,
                'geometry' => $geometry->toArray(),
            ];
        });

        return json_encode(
            [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<mixed>
     */
    abstract public function getCoordinates(): array;

    /**
     * @param  array<string>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new GeometryCast(static::class);
    }

    public function toSqlExpression(ConnectionInterface $connection): ExpressionContract
    {
        $wkt = addslashes($this->toWkt());

        if (! AxisOrder::supported($connection)) {
            return DB::raw((new GeometryExpression("ST_GeomFromText('{$wkt}', {$this->srid})"))->normalize($connection));
        }

        return DB::raw((new GeometryExpression("ST_GeomFromText('{$wkt}', {$this->srid}, 'axis-order=long-lat')"))->normalize($connection));
    }

    public function toBoundingBox(float $minPadding = 0): BoundingBox
    {
        return BoundingBox::fromGeometry($this, $minPadding);
    }
}
