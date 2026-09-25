# Changelog

All notable changes to `jackardios/laravel-eloquent-spatial` are documented in this file.

This package is a fork of [matanyadaev/laravel-eloquent-spatial](https://github.com/MatanYadaev/laravel-eloquent-spatial).
For the history before the fork, see the [upstream changelog](https://github.com/MatanYadaev/laravel-eloquent-spatial/blob/master/CHANGELOG.md).

## v4.1.0 - 2026-09-25

### Security

- The `$operator` of `whereDistance`, `whereDistanceSphere` and `whereSrid`, the `$direction` of `orderByDistance` and `orderByDistanceSphere`, and the `$alias` of `withDistance` and `withDistanceSphere` were written into the SQL unchecked, which allowed SQL injection when these values came from user input. Operators other than `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`, and directions other than `asc` and `desc`, now throw `InvalidArgumentException`, and the alias is quoted as a column name.

### Added

- Laravel 13 support.

### Fixed

- PHP 8.5: parsing and `toWkb()` no longer fail with `Class "Point" not found` when the application turns deprecations into exceptions. geoPHP's compile-time deprecations are now silenced while it loads.
- The service provider no longer extends Laravel's `DatabaseServiceProvider`. Registering it registered the database services a second time and replaced the already-resolved `db` manager.
- MySQL axis-order support is detected once per database connection instead of for every geometry.

### Documentation

- `UPGRADE.md` no longer claims that `fromWkt()` and `fromWkb()` skip coordinate validation; they validate like the `Point` constructor.
- README: added Laravel 10 migration syntax and database limitations.

## v4.0.0 - 2026-01-11

- Namespace changed from `MatanYadaev\EloquentSpatial` to `Jackardios\EloquentSpatial`.
- `Point` validates longitude (-180..180) and latitude (-90..90).
- `BoundingBox` with antimeridian support, usable as an Eloquent cast (`geometry` or `json` storage).
- `whereOverlaps` scope.

See [UPGRADE.md](UPGRADE.md#upgrading-from-v3x-to-v40) for details.
