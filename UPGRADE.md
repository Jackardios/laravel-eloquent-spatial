# Upgrade Guide

## Upgrading from v4.x to v5.0

v5.0 replaces the unmaintained geoPHP library with [brick/geo](https://github.com/brick/geo) and its own WKB reader, drops old Laravel and PHP versions, and fixes bugs whose fixes change behaviour. Most applications need only the steps below. The sections after them list every change that code or tests may notice.

### Requirements

- PHP 8.3+
- Laravel 12.18+ or 13.x

Version 4.x keeps supporting PHP 8.1+ and Laravel 10 to 13.

### Steps

1. Update the package:

```bash
composer require jackardios/laravel-eloquent-spatial:^5.0
```

2. The package no longer has a service provider. If you registered `Jackardios\EloquentSpatial\EloquentSpatialServiceProvider` by hand, in `bootstrap/providers.php` or `config/app.php`, remove it.

3. The Doctrine DBAL types are removed, because Laravel 11+ does not use Doctrine DBAL. Remove any references to classes in `Jackardios\EloquentSpatial\Doctrine`.

4. If you read spatial data in formats other than WKT, WKB and GeoJSON, convert it before reading, or read it with brick/geo or another library. See [Parsing](#parsing).

5. Run your test suite, and check the sections below for the changes that it reports.

### Security fixes

In v4.0 and earlier, the `$operator` of `whereDistance`, `whereDistanceSphere` and `whereSrid`, the `$direction` of `orderByDistance` and `orderByDistanceSphere`, and the `$alias` of `withDistance` and `withDistanceSphere` were written into the SQL unchanged. If these values came from a request, the query could be changed with SQL injection. This is fixed in v5.0 and in v4.1.0:

- The operator must be one of `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`. Anything else throws `InvalidArgumentException`.
- The direction must be `asc` or `desc`, in any case. Anything else throws `InvalidArgumentException`.
- The alias is quoted as a column name. An alias such as `'distance'` works as before. On PostgreSQL, an alias with capital letters keeps them: the alias `'myDistance'` is now the attribute `myDistance`, where v4.0 returned `mydistance`.

Other values that v5 rejects to protect the application:

- `Geometry::toSqlExpression()` throws `InvalidArgumentException` if `toWkt()` returns characters that WKT does not use. v4 escaped the WKT with `addslashes()`, which is not correct for PostgreSQL. This affects only subclasses that override `toWkt()`.
- Geometries nested more than 64 levels deep throw `InvalidArgumentException` when they are read. Reading much deeper nesting could crash PHP.
- `BoundingBox::fromPoints()` throws `InvalidArgumentException` for a `$minPadding` of `NAN` or `INF`. In v4, an infinite or very large padding made it loop forever.

### Parsing

`fromWkt()`, `fromJson()` and `Factory::parse()` now read WKT and GeoJSON with brick/geo instead of geoPHP, and WKB with the package's own reader.

**Each method reads only its format.** In v4, every method detected the format itself.

| Input | v4 | v5 |
|---|---|---|
| `Point::fromWkt('{"type":"Point","coordinates":[1,2]}')` | Point | throws |
| `Point::fromWkt('0101000000...')` (hex WKB) | Point | throws |
| `Point::fromJson('POINT(1 2)')` | Point | throws |

`Factory::parse()` still detects the format, but only WKT, EWKT, GeoJSON, WKB, EWKB and hex WKB or EWKB. KML, GPX, GeoRSS and geohashes are no longer read.

**SRIDs.**

- `fromWkt('SRID=4326;POINT(1 2)')` without an `$srid` returns a geometry with SRID 4326; v4 ignored the EWKT SRID.
- `Factory::parse()` also reads the SRID of EWKT, EWKB and the WKB that MySQL stores.
- The geometries inside a parsed collection, line string or polygon now have the SRID of the parsed geometry; in v4 they had SRID 0.
- The `crs` member of GeoJSON is ignored, as in v4.

**WKB.**

- `fromWkb()` now also reads WKB without the MySQL SRID prefix, big-endian WKB, and binary EWKB.
- Hex WKB with the MySQL SRID prefix was read with wrong coordinates in v4 and is now read correctly.
- WKB with extra bytes after the geometry throws.
- WKB that is too short throws without a PHP warning.

**Stricter input.** These values were accepted in v4 and throw `InvalidArgumentException` in v5:

- WKT with three coordinates but without `Z`, such as `POINT(1 2 3)`. Write `POINT Z(1 2 3)`; Z and M coordinates are read and dropped, as in v4.
- WKT numbers written as `+1` or `.5`.
- GeoJSON coordinates that are strings, such as `["1", "2"]`.

**More lenient input.** Lowercase WKT and extra spaces, such as `point ( 1 2 )`, are now accepted.

**Errors.** Invalid input throws `InvalidArgumentException`. In v4, some invalid WKB threw a `TypeError`, and some invalid GeoJSON first emitted a PHP warning, which Laravel turns into an `ErrorException`. The messages have changed, so match on the exception class rather than the message.

### Point

- Longitude and latitude ranges are checked only for SRID 0 and 4326. A `Point` with another SRID, such as Web Mercator (3857) in metres, is no longer rejected for being out of range.
- `NAN` and `INF` coordinates always throw `InvalidArgumentException`: *Coordinates must be finite numbers*. v4 accepted `NAN`.
- `toWkt()` writes the shortest number that reads back as the same float, instead of rounding to the `precision` setting (14 digits by default). For example, `new Point(0.1234567890123456789, 1 / 3)` is now `POINT(0.12345678901234568 0.3333333333333333)`, not `POINT(0.12345678901235 0.33333333333333)`. Values that are saved and read back no longer lose precision. If your tests compare WKT strings, update the expected values.

### BoundingBox

- A box with the same bottom and top latitude, such as a box of a single point or a horizontal line, is now allowed. `BoundingBox::fromPoints()` with one point and no padding returns such a box instead of throwing. Only a bottom latitude greater than the top latitude throws `InvalidBoundingBoxPoints`, now with the message *The latitude of the bottom point must not be greater than the latitude of the top point*. **If your tests expect an exception for a single point, update them.**
- `BoundingBox::fromPoints()` with a `$minPadding` of 360 or more returns the whole longitude range. v4 returned a wrong box.
- The box keeps copies of the points it is given, and `getLeftBottom()` and `getRightTop()` return copies. Changing a returned point no longer changes the box. `$box->getLeftBottom() === $box->getLeftBottom()` is now `false`.
- `toPolygon()` and `toGeometry()` accept an optional SRID. If you extend `BoundingBox` and override `toPolygon()`, `toGeometry()` or `createPolygon()`, update the signatures: `toPolygon(int|Srid|null $srid = null)`, `toGeometry(int|Srid|null $srid = null)` and `createPolygon(float $left, float $right, int $srid)`.

**Bounding box casts in the geometry format:**

- A box wider than 180 degrees, such as the whole world, is read back correctly. v4 read it as the rest of the world or as a narrow strip, and a model with `HasSpatial` saved that wrong value back on every save.
- An unchanged box is no longer written on every save.
- The cast can store the geometry with an SRID: `BoundingBox::class.':geometry,4326'`. On MySQL 8, a column with an SRID rejects geometries with another SRID, so v4 could not save a box to such a column.

### Dirty checks and casts

- `HasSpatial` no longer overrides `originalIsEquivalent()`. The geometry and bounding box casts compare values themselves with `ComparesCastableAttributes`, which requires Laravel 12.18. Changes are therefore detected in models without the trait too.
- A change of only the SRID, or only the geometry type, such as a `Point` replaced by a `MultiPoint` with the same coordinates, is now saved. v4 ignored it.
- A geometry cast accepts subclasses of its class that have the same geometry type, such as a class registered with `EloquentSpatial::usePoint()` for a `Point::class` cast. In v4, a model could not save a value that it had just read with such a class.

### GeoJSON and WKB output

- Subclasses are written as their geometry type. `toJson()` of a class that extends `Point` gives `"type":"Point"` instead of the class name. A class that extends `GeometryCollection` gives `geometries` instead of `coordinates`. v4 also could not write subclasses as WKB.
- `toFeatureCollectionJson()` writes `"properties":{}` instead of `"properties":[]`, as GeoJSON requires. `fromJson()` still reads `[]`.
- `GeometryCollection::toArray()['geometries']` is an array instead of a `Collection`. The JSON is the same.

### GeometryCollection and its subclasses

These changes apply to `MultiPoint`, `LineString`, `Polygon` and the other collections too.

- Reading an offset that does not exist, `$collection[5]`, throws `OutOfBoundsException`. v4 emitted a warning and then threw a `TypeError`.
- Setting an offset after the last geometry appends the geometry, so the collection stays a list. In v4 it created a gap, and the GeoJSON had an object instead of an array.
- A geometry of the wrong type throws `InvalidArgumentException`, and an `unset()` that would leave too few geometries throws `InvalidArgumentException` too. Both now happen before the collection is changed. v4 changed the collection first, and for some wrong types threw a `TypeError`, such as a `LineString` set in a `MultiPoint`.
- The constructor copies the given array or collection, so later changes to it do not change the geometry.
- The message for too few geometries says *at least 1 entry* instead of *at least 1 entries*.

### Removed

- The service provider `EloquentSpatialServiceProvider`, and its auto-discovery entry.
- The Doctrine DBAL types in `Jackardios\EloquentSpatial\Doctrine`.
- `HasSpatial::originalIsEquivalent()`.
- `Factory::loadGeoPhp()` and `Factory::createFromGeometry()`, and the `phayes/geophp` dependency.

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
