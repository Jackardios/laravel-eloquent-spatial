# Upgrade Guide

## Upgrading from v4.0 to v4.1

v4.1 adds Laravel 13 support and fixes an SQL injection. Code that passes valid values is not affected.

### Requirements

- PHP 8.1+ (Laravel 13 itself requires PHP 8.3+)
- Laravel 10.x, 11.x, 12.x or 13.x

### Steps

1. Update the package:

```bash
composer update jackardios/laravel-eloquent-spatial
```

2. If you upgrade Laravel to 13 in the same step, require both together:

```bash
composer require laravel/framework:^13.0 jackardios/laravel-eloquent-spatial:^4.1 --with-all-dependencies
```

3. The distance and SRID scopes now reject values that could change the SQL. `whereDistance`, `whereDistanceSphere` and `whereSrid` accept only the operators `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`, and `orderByDistance` and `orderByDistanceSphere` only the directions `asc` and `desc`, in any case. Other values throw `InvalidArgumentException`. The alias of `withDistance` and `withDistanceSphere` is quoted as a column name, so an alias such as `'distance'` works as before, but an alias that contains SQL no longer does. On PostgreSQL, an alias with capital letters keeps them: the alias `'myDistance'` is now the attribute `myDistance`, where v4.0 returned `mydistance`.

4. Only if your code treats `EloquentSpatialServiceProvider` as a `DatabaseServiceProvider` (for example `instanceof` checks or `$app->getProviders(DatabaseServiceProvider::class)`): the provider now extends `Illuminate\Support\ServiceProvider`. Laravel's own `DatabaseServiceProvider` still registers the database services.

## Upgrading from v3.x to v4.0

This major release introduces a namespace change and several improvements to the library.

### Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x

### Breaking Changes

#### 1. Namespace Change

The package namespace has changed from `MatanYadaev\EloquentSpatial` to `Jackardios\EloquentSpatial`.

**Before (v3.x):**

```php
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;
use MatanYadaev\EloquentSpatial\EloquentSpatial;
use MatanYadaev\EloquentSpatial\Enums\Srid;
```

**After (v4.x):**

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Traits\HasSpatial;
use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
```

**Migration steps:**

1. Update your `composer.json`:

```bash
composer remove matanyadaev/laravel-eloquent-spatial
composer require jackardios/laravel-eloquent-spatial
```

2. Find and replace all namespace occurrences:

```bash
# macOS/Linux
find . -type f -name "*.php" -exec sed -i '' 's/MatanYadaev\\EloquentSpatial/Jackardios\\EloquentSpatial/g' {} +

# Linux (GNU sed)
find . -type f -name "*.php" -exec sed -i 's/MatanYadaev\\EloquentSpatial/Jackardios\\EloquentSpatial/g' {} +
```

Or use your IDE's search and replace functionality.

3. Clear application caches:

```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

#### 2. Coordinate Validation (Breaking)

The `Point` constructor now validates coordinates:

- Longitude must be between -180 and 180
- Latitude must be between -90 and 90

```php
// Valid coordinates - works as before
$point = new Point(100.0, 50.0);

// Invalid coordinates now throw InvalidArgumentException
$point = new Point(200, 0);  // Throws: "Longitude must be between -180 and 180"
$point = new Point(0, 100);  // Throws: "Latitude must be between -90 and 90"
```

**Action required:** If your application stores coordinates outside valid geographic ranges, you must fix the data before upgrading. Run a database query to identify invalid coordinates:

```sql
-- MySQL, MariaDB and PostGIS
SELECT * FROM your_table
WHERE ST_X(location) < -180
   OR ST_X(location) > 180
   OR ST_Y(location) < -90
   OR ST_Y(location) > 90;
```

### New Features

#### BoundingBox with Antimeridian Support

The `BoundingBox` class now properly handles geographic regions that cross the antimeridian (180/-180 longitude line):

```php
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Point;

// Create a bounding box crossing the antimeridian (e.g., Pacific region)
$bbox = new BoundingBox(
    leftBottom: new Point(170, -10),   // Eastern hemisphere
    rightTop: new Point(-170, 10)       // Western hemisphere
);

// Check if it crosses
$bbox->crossesAntimeridian(); // true

// Convert to geometry - returns MultiPolygon for antimeridian-crossing boxes
$geometry = $bbox->toGeometry(); // MultiPolygon with two parts
```

#### BoundingBox Eloquent Cast

`BoundingBox` can now be used as an Eloquent cast:

```php
class Region extends Model
{
    protected $casts = [
        // Store as geometry column
        'bounds' => BoundingBox::class,

        // Store as JSON column
        'bounds' => BoundingBox::class . ':json',
    ];
}
```

#### whereOverlaps Scope

New spatial query scope for detecting overlapping geometries:

```php
Place::whereOverlaps('area', $polygon)->get();
```

### Bug Fixes

- **WKB parsing safety**: `Geometry::fromWkb()` throws `InvalidArgumentException` when the input is too short to contain an SRID. PHP emits an `unpack()` warning first, which Laravel converts to an `ErrorException`
- **Exception handling**: `Factory::parse()` now properly propagates exceptions from the geoPHP library instead of masking them
- **BoundingBox validation**: Fixed error message typo in latitude constraint validation
- **SQL escaping**: WKT strings are now escaped when building SQL expressions to prevent issues with special characters

### Internal Improvements

These changes don't affect the public API:

- `declare(strict_types=1)` added to all PHP files
- `BoundingBoxCast` extracted to a dedicated class
- `Helper::parseStGeomFromText()` method for code deduplication
- `getDistanceSphereFunction()` method in `HasSpatial` trait for database-agnostic queries

### Testing Your Upgrade

After upgrading, verify everything works:

```bash
# Run your test suite
php artisan test
# or
./vendor/bin/pest

# Check for any remaining old namespace references
grep -r "MatanYadaev\\\\EloquentSpatial" app/ --include="*.php"
```

### Troubleshooting

**Class not found errors:**

```bash
composer dump-autoload
php artisan config:clear
```

**Coordinate validation errors:**

All ways of creating a `Point` validate coordinates, including `fromWkt()`, `fromWkb()`, `fromJson()` and `fromArray()`. This also applies to values read from the database: loading a model whose column holds an out-of-range point throws `InvalidArgumentException`.

Find and fix such rows before upgrading, for example with the query from [Coordinate Validation](#2-coordinate-validation-breaking). On MySQL 8, a column with a geographic SRID such as 4326 cannot hold out-of-range points in the first place.
