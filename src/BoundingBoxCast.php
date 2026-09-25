<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use JsonException;
use Throwable;

class BoundingBoxCast implements CastsAttributes, ComparesCastableAttributes
{
    public const FORMAT_GEOMETRY = 'geometry';

    public const FORMAT_JSON = 'json';

    private string $format;

    public function __construct(string $format = self::FORMAT_GEOMETRY)
    {
        if (! in_array($format, [self::FORMAT_GEOMETRY, self::FORMAT_JSON], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid format "%s". Supported formats: %s, %s', $format, self::FORMAT_GEOMETRY, self::FORMAT_JSON)
            );
        }

        $this->format = $format;
    }

    /**
     * @param  Model  $model
     * @param  string|ExpressionContract|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?BoundingBox
    {
        if (! $value) {
            return null;
        }

        if ($this->format === self::FORMAT_JSON) {
            if (! is_string($value)) {
                throw new InvalidArgumentException('JSON format expects string value from database');
            }

            return $this->fromJson($value);
        }

        return $this->fromGeometry($model, $value);
    }

    /**
     * @param  string|ExpressionContract  $value
     */
    private function fromGeometry(Model $model, $value): BoundingBox
    {
        if ($value instanceof ExpressionContract) {
            $grammar = $model->getConnection()->getQueryGrammar();
            $expressionValue = (string) $value->getValue($grammar);
            ['wkt' => $wkt, 'srid' => $srid] = Helper::parseStGeomFromText($expressionValue);

            return $this->geometryToBoundingBox(Geometry::fromWkt($wkt, $srid));
        }

        return $this->geometryToBoundingBox(Geometry::fromWkb($value));
    }

    private function fromJson(string $value): BoundingBox
    {
        try {
            /** @var array<string, mixed> $array */
            $array = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return BoundingBox::fromArray($array);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON for BoundingBox: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Reads the corners of the Polygon or MultiPolygon that BoundingBox::toGeometry() writes.
     *
     * BoundingBox::fromGeometry() is not used for them: it takes the shortest longitude arc, so it would read a box
     * wider than 180 degrees as the rest of the world.
     */
    private function geometryToBoundingBox(Geometry $geometry): BoundingBox
    {
        if ($geometry instanceof Polygon) {
            // A polygon cannot cross the antimeridian, so its extent is the box.
            [$left, $bottom, $right, $top] = $this->extent($geometry);

            return new BoundingBox(new Point($left, $bottom), new Point($right, $top));
        }

        if ($geometry instanceof MultiPolygon) {
            $polygons = $geometry->getGeometries()->values()->all();

            if (count($polygons) === 2) {
                // A box across the antimeridian is written as its eastern part up to 180 and its western part from -180.
                [$left, $eastBottom, $eastRight, $eastTop] = $this->extent($polygons[0]);
                [$westLeft, $westBottom, $right, $westTop] = $this->extent($polygons[1]);

                if ($eastRight === 180.0 && $westLeft === -180.0) {
                    return new BoundingBox(
                        new Point($left, min($eastBottom, $westBottom)),
                        new Point($right, max($eastTop, $westTop)),
                    );
                }
            }

            return $geometry->toBoundingBox();
        }

        throw new InvalidArgumentException(
            sprintf('Expected Polygon or MultiPolygon, %s given.', $geometry::class)
        );
    }

    /**
     * @return array{float, float, float, float} The left, bottom, right and top.
     */
    private function extent(Polygon $polygon): array
    {
        [$left, $bottom, $right, $top] = [INF, INF, -INF, -INF];

        foreach ($polygon->getPoints() as $point) {
            $left = min($left, $point->longitude);
            $bottom = min($bottom, $point->latitude);
            $right = max($right, $point->longitude);
            $top = max($top, $point->latitude);
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * @param  Model  $model
     * @param  BoundingBox|array<string, float>|mixed|null  $value
     * @param  array<string, mixed>  $attributes
     * @return ExpressionContract|string|null
     *
     * @throws InvalidArgumentException
     */
    public function set($model, string $key, $value, array $attributes): mixed
    {
        if (! $value) {
            return null;
        }

        if (is_array($value)) {
            // @phpstan-ignore argument.type
            $value = BoundingBox::fromArray($value);
        }

        if ($value instanceof ExpressionContract) {
            return $value;
        }

        if (! ($value instanceof BoundingBox)) {
            $bboxType = is_object($value) ? $value::class : gettype($value);
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', BoundingBox::class, $bboxType)
            );
        }

        if ($this->format === self::FORMAT_JSON) {
            return $value->toJson();
        }

        return $value->toGeometry()->toSqlExpression($model->getConnection());
    }

    /**
     * Compares the bounds rather than the stored representation, which the database may normalise.
     *
     * @param  Model  $model
     * @param  string|ExpressionContract|null  $firstValue
     * @param  string|ExpressionContract|null  $secondValue
     */
    public function compare($model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        try {
            $first = $this->get($model, $key, $firstValue, []);
            $second = $this->get($model, $key, $secondValue, []);
        } catch (Throwable) {
            // An unreadable value is treated as changed, so that it is saved rather than kept.
            return false;
        }

        return $first?->toArray() === $second?->toArray();
    }
}
