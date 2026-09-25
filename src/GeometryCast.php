<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Throwable;

class GeometryCast implements CastsAttributes, ComparesCastableAttributes
{
    /** @var class-string<Geometry> */
    private string $className;

    /**
     * @param  class-string<Geometry>  $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * @param  Model  $model
     * @param  string|ExpressionContract|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?Geometry
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof ExpressionContract) {
            ['wkt' => $wkt, 'srid' => $srid] = $this->extractValuesFromExpression($value, $model->getConnection());

            return $this->className::fromWkt($wkt, $srid);
        }

        return $this->className::fromWkb($value);
    }

    /**
     * @param  Model  $model
     * @param  Geometry|mixed|null  $value
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function set($model, string $key, $value, array $attributes): ?ExpressionContract
    {
        if (! $value) {
            return null;
        }

        if (is_array($value)) {
            // @phpstan-ignore argument.type
            $value = Geometry::fromArray($value);
        }

        if ($value instanceof ExpressionContract) {
            return $value;
        }

        if (! $this->isCorrectGeometryType($value)) {
            $geometryType = is_object($value) ? $value::class : gettype($value);
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', $this->className, $geometryType)
            );
        }

        /** @var Geometry $value */

        return $value->toSqlExpression($model->getConnection());
    }

    /**
     * Compares the stored values, so that a change of only the SRID or the geometry type is saved.
     *
     * @param  Model  $model
     * @param  string|ExpressionContract|null  $firstValue
     * @param  string|ExpressionContract|null  $secondValue
     */
    public function compare($model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        try {
            return $this->comparableValue($model, $firstValue) === $this->comparableValue($model, $secondValue);
        } catch (Throwable) {
            // An unreadable value is treated as changed, so that it is saved rather than kept.
            return false;
        }
    }

    /**
     * The WKT and SRID. The WKT of an expression is compared as it is, without reading it: set() writes it with
     * toWkt(), and a value that differs only in its formatting is at worst saved again.
     *
     * @param  string|ExpressionContract|null  $value
     * @return array{string, int}|null
     */
    private function comparableValue(Model $model, mixed $value): ?array
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof ExpressionContract) {
            ['wkt' => $wkt, 'srid' => $srid] = $this->extractValuesFromExpression($value, $model->getConnection());

            return [$wkt, $srid];
        }

        $geometry = $this->className::fromWkb($value);

        return [$geometry->toWkt(), $geometry->srid];
    }

    /**
     * A subclass is accepted, such as the class registered with EloquentSpatial::usePoint() for a Point cast, as long
     * as it is the same type: a Polygon is also a MultiLineString, but cannot be stored as one.
     */
    private function isCorrectGeometryType(mixed $value): bool
    {
        if (! $value instanceof $this->className) {
            return false;
        }

        $type = Helper::geometryType($this->className);

        return $type === null || Helper::geometryType($value) === $type;
    }

    /**
     * @return array{wkt: string, srid: int}
     */
    private function extractValuesFromExpression(ExpressionContract $expression, Connection $connection): array
    {
        $grammar = $connection->getQueryGrammar();
        $expressionValue = (string) $expression->getValue($grammar);

        return Helper::parseStGeomFromText($expressionValue);
    }
}
