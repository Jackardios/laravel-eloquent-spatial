# Upgrade Guide

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
-- MySQL/MariaDB
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

- **WKB parsing safety**: `Geometry::fromWkb()` now validates binary data before extracting SRID, preventing errors with malformed WKB input
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

If you have existing data with invalid coordinates, you can temporarily bypass validation by not using the `Point` constructor directly. Instead, use `fromWkt()` or `fromWkb()` which parse existing data without validation:

```php
// These methods don't validate coordinates
$point = Point::fromWkt('POINT(200 100)');
```

However, this is not recommended for new data. Fix your data to use valid geographic coordinates.
