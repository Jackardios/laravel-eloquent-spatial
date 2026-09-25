# Changelog

All notable changes to `jackardios/laravel-eloquent-spatial` are documented in this file.

This package is a fork of [matanyadaev/laravel-eloquent-spatial](https://github.com/MatanYadaev/laravel-eloquent-spatial).
For the history before the fork, see the [upstream changelog](https://github.com/MatanYadaev/laravel-eloquent-spatial/blob/master/CHANGELOG.md).

## Unreleased (5.0.0)

See [UPGRADE.md](UPGRADE.md#upgrading-from-v4x-to-v50) for the details of every change.

### Changed

- Requires PHP 8.3+ and Laravel 12.18+ or 13.x.
- WKT and GeoJSON are read with brick/geo instead of geoPHP, which is unmaintained and emits deprecations on PHP 8.5. WKB is read and written by the package; reading geometries from the database is faster than in 4.x.
- `fromWkt()`, `fromJson()` and `fromWkb()` read only their own format. `Factory::parse()` detects WKT, EWKT, GeoJSON, WKB, EWKB and hex WKB or EWKB, and no longer reads KML, GPX, GeoRSS or geohashes.
- `fromWkt()` and `Factory::parse()` read the SRID of EWKT, and `Factory::parse()` reads the SRID of EWKB and MySQL WKB. The geometries inside a parsed geometry have its SRID.
- `Point` checks the longitude and latitude ranges only for SRID 0 and 4326.
- `toWkt()` writes coordinates without rounding them to the `precision` setting.
- A `BoundingBox` can have zero height, and `getLeftBottom()` and `getRightTop()` return copies.
- Dirty checks are done by the casts (`ComparesCastableAttributes`) instead of `HasSpatial::originalIsEquivalent()`.
- Geometry casts accept subclasses of the same geometry type. Subclasses are written to GeoJSON and WKB as their geometry type.
- `GeometryCollection` stays a list and stays valid when it is changed as an array. A missing offset throws `OutOfBoundsException`.
- `toFeatureCollectionJson()` writes `"properties":{}`, and `GeometryCollection::toArray()` returns the geometries as an array.

### Added

- An SRID option for the bounding box cast in the geometry format, `BoundingBox::class.':geometry,4326'`, and an optional SRID for `BoundingBox::toPolygon()` and `toGeometry()`.

### Fixed

- **Security:** the operators, order directions and aliases of the distance and SRID scopes are no longer written into the SQL unchecked, which allowed SQL injection. Also fixed in 4.1.0.
- `NAN` and `INF` coordinates are rejected.
- `BoundingBox::fromPoints()` no longer loops forever for an infinite or very large padding, and returns the whole longitude range for a padding of 360 or more.
- Deeply nested geometries and invalid WKB no longer crash PHP or allocate large amounts of memory; nesting is limited to 64 levels.
- A bounding box wider than 180 degrees stored as a geometry is read back correctly, and an unchanged bounding box is no longer written on every save.
- A change of only the SRID or the geometry type is saved.
- `toSqlExpression()` accepts only WKT characters instead of escaping the WKT with `addslashes()`.
- Hex WKB with the MySQL SRID prefix is read with the right coordinates.

### Removed

- The service provider, the Doctrine DBAL types, `HasSpatial::originalIsEquivalent()`, `Factory::loadGeoPhp()` and the `phayes/geophp` dependency.

## v4.1.0 - 2026-09-25

### Security

- The `$operator` of `whereDistance`, `whereDistanceSphere` and `whereSrid`, the `$direction` of `orderByDistance` and `orderByDistanceSphere`, and the `$alias` of `withDistance` and `withDistanceSphere` were written into the SQL unchecked, which allowed SQL injection when these values came from user input. Operators other than `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`, and directions other than `asc` and `desc`, now throw `InvalidArgumentException`, and the alias is quoted as a column name.

### Added

- Laravel 13 support.

### Fixed

- PHP 8.5: parsing and `toWkb()` no longer fail with `Class "Point" not found` when the application turns deprecations into exceptions. geoPHP's compile-time deprecations are now silenced while it loads.
- The service provider no longer extends Laravel's `DatabaseServiceProvider`. Registering it registered the database services a second time and replaced the already-resolved `db` manager.
- MySQL axis-order support is detected once per database connection instead of for every geometry, and a `MariaDbConnection` (Laravel 11+) no longer opens a connection just for this check.

### Documentation

- `UPGRADE.md` no longer claims that `fromWkt()` and `fromWkb()` skip coordinate validation; they validate like the `Point` constructor.
- README: added Laravel 10 migration syntax and database limitations.

## v4.0.0 - 2026-01-11

- Namespace changed from `MatanYadaev\EloquentSpatial` to `Jackardios\EloquentSpatial`.
- `Point` validates longitude (-180..180) and latitude (-90..90).
- `BoundingBox` with antimeridian support, usable as an Eloquent cast (`geometry` or `json` storage).
- `whereOverlaps` scope.

See [UPGRADE.md](UPGRADE.md#upgrading-from-v3x-to-v40) for details.
