<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial\Objects;

use ArrayAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Helper;
use OutOfBoundsException;

class GeometryCollection extends Geometry implements ArrayAccess
{
    /** @var Collection<int, Geometry> */
    protected Collection $geometries;

    protected string $collectionOf = Geometry::class;

    protected int $minimumGeometries = 0;

    /**
     * @param  Collection<int, Geometry>|array<int, Geometry>  $geometries
     *
     * @throws InvalidArgumentException
     */
    public function __construct(Collection|array $geometries, int|Srid|null $srid = null)
    {
        // A copy, so that a change to the given collection cannot bypass the validation. The keys are dropped, so
        // that the coordinates are a list in GeoJSON.
        $this->geometries = new Collection(array_values(is_array($geometries) ? $geometries : $geometries->all()));
        $this->srid = Helper::getSrid($srid);

        $this->validateGeometriesType();
        $this->validateGeometriesCount();
    }

    public function toWkt(): string
    {
        $wktData = $this->getWktData();

        if ($wktData === '') {
            return 'GEOMETRYCOLLECTION EMPTY';
        }

        return "GEOMETRYCOLLECTION({$wktData})";
    }

    public function getWktData(): string
    {
        return $this->geometries
            ->map(static function (Geometry $geometry): string {
                return $geometry->toWkt();
            })
            ->join(', ');
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function getCoordinates(): array
    {
        return $this->geometries
            ->map(static function (Geometry $geometry): array {
                return $geometry->getCoordinates();
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // MultiPoint, LineString and the other subclasses have coordinates instead of geometries.
        if (Helper::geometryType($this) !== 'GeometryCollection') {
            return parent::toArray();
        }

        return [
            'type' => 'GeometryCollection',
            'geometries' => $this->geometries->map(static function (Geometry $geometry): array {
                return $geometry->toArray();
            })->all(),
        ];
    }

    /**
     * @return Collection<int, Geometry>
     */
    public function getGeometries(): Collection
    {
        return new Collection($this->geometries->all());
    }

    /**
     * @return Collection<int, Point>
     */
    public function getPoints(): Collection
    {
        $points = [];

        foreach ($this->geometries as $geometry) {
            if ($geometry instanceof Point) {
                $points[] = $geometry;
            } elseif ($geometry instanceof self) {
                foreach ($geometry->getPoints() as $point) {
                    $points[] = $point;
                }
            }
        }

        return new Collection($points);
    }

    /**
     * @param  mixed  $offset
     */
    public function offsetExists($offset): bool
    {
        $offset = self::normalizeOffset($offset);

        return is_int($offset) && isset($this->geometries[$offset]);
    }

    /**
     * @param  mixed  $offset
     *
     * @throws OutOfBoundsException
     */
    public function offsetGet($offset): Geometry
    {
        $offset = self::normalizeOffset($offset);
        $geometry = is_int($offset) ? $this->geometries->get($offset) : null;

        if ($geometry === null) {
            throw new OutOfBoundsException(sprintf('%s has no geometry at offset %s.', static::class, var_export($offset, true)));
        }

        return $geometry;
    }

    /**
     * An offset after the last geometry appends the geometry.
     *
     * @param  mixed  $offset
     * @param  Geometry  $value
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function offsetSet($offset, $value): void
    {
        if (! $value instanceof $this->collectionOf) {
            throw new InvalidArgumentException(sprintf('%s must be a collection of %s', static::class, $this->collectionOf));
        }

        $offset = self::normalizeOffset($offset);

        if ($offset === null || (is_int($offset) && $offset >= $this->geometries->count())) {
            $this->geometries->push($value);

            return;
        }

        if (! is_int($offset) || ! isset($this->geometries[$offset])) {
            throw new OutOfBoundsException(sprintf('%s has no geometry at offset %s.', static::class, var_export($offset, true)));
        }

        $this->geometries[$offset] = $value;
    }

    /**
     * The geometries after the offset move down by one, as in a list.
     *
     * @param  mixed  $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetUnset($offset): void
    {
        $offset = self::normalizeOffset($offset);

        if (! is_int($offset) || ! isset($this->geometries[$offset])) {
            return;
        }

        if ($this->geometries->count() - 1 < $this->minimumGeometries) {
            throw $this->tooFewGeometries();
        }

        $this->geometries->splice($offset, 1);
    }

    /**
     * A string that is an integer, such as "1", is that offset, as for an array. Other strings, such as "01", are not.
     */
    private static function normalizeOffset(mixed $offset): mixed
    {
        return is_string($offset) && (string) (int) $offset === $offset ? (int) $offset : $offset;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateGeometriesCount(): void
    {
        if ($this->geometries->count() < $this->minimumGeometries) {
            throw $this->tooFewGeometries();
        }
    }

    private function tooFewGeometries(): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf(
                '%s must contain at least %d %s',
                static::class,
                $this->minimumGeometries,
                Str::plural('entry', $this->minimumGeometries)
            )
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateGeometriesType(): void
    {
        foreach ($this->geometries as $geometry) {
            if (! ($geometry instanceof $this->collectionOf)) {
                throw new InvalidArgumentException(
                    sprintf('%s must be a collection of %s', static::class, $this->collectionOf)
                );
            }
        }
    }
}
