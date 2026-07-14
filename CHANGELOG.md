# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.1.0] - 2026-07-15

### Added
- `afterInsert()` hook which runs after rows are inserted, before their IDs are returned.
It receives the inserted rows and their IDs, enabling follow-up work that depends on a successful insert.

### Changed
- PHP 8.2+ is now required.


## [4.0.0] - 2025-01-03

### Changed
- `getMap()` is no longer abstract and returns an empty array by default,
so it is no longer necessary to implement for read-only APIs.
- If the ID column is returned from `getMap()`, it is now automatically writable
without having to set a `writableId` flag.
- `getSelectMap()` now returns an empty array by default (rather than the `getMap()` value),
so it is no longer necessary to implement this method just to prevent selecting write-only
properties when using `getSelectProps()`.

> [!Important]
> When upgrading, remove readonly ID properties from `getMap()`, and implement
> `getSelectMap()` if any writable property is not returned by `getSelectProps()`.

### Removed
- Unnecessary `getDefaultValues()` method. Defaults can be set via `processValues()` instead.
- `writableId` bool property.


## [3.0.0] - 2024-10-29

### Added
- Official support for PostgreSQL.

### Changed
- Moved to `DevTheorem` namespace.
- Phaster now validates that property values are scalar or null when inserting and updating rows.
- PHP 8.1+ is now required.

### Removed
- All previously deprecated methods.


## [2.9.0] - 2024-09-10

### Added
- `processValues()` now allows setting the ID of an existing row on the returned object, in which
case a new row will not be inserted, and the specified ID will be returned in the list of row IDs.


## [2.8.0] - 2023-12-22

### Added
- `countEntities()` method and corresponding `count()` route handler.
This allows counting the rows that match a query/filter without selecting them.


## [2.7.0] - 2023-09-27

### Added
- `writableId` bool property to optionally make the ID column writable.


## [2.6.0] - 2023-08-01

### Added
- `output` bool parameter on `Prop` constructor.

### Changed
- `col` is now optional when constructing `Prop` with a `getValue` function.

### Fixed
- Error when a `Prop` depends on a property declared in one of the `get*Map` methods.


## [2.5.0] - 2023-03-05

### Changed
- Minor code cleanup and refactoring.

### Deprecated
- `getDuplicateError()` and `getConstraintError()` methods. These tend to just result in unhelpful
error messages which lack important details about why the conflict occurred. A better approach
is to add custom checks and errors for conflicts that can occur during normal usage.
- Unnecessary `QueryOptions` methods in favor of readonly properties.


## [2.4.0] - 2022-11-06

### Added
- `getSelectProps()` method as a preferred alternative to `getPropMap()` when using PHP 8+.

### Changed
- Moved most internal methods to a separate class for improved code maintainability.

### Deprecated
- `getPropMap()` method. Use `getSelectProps()` instead.


## [2.3.0] - 2021-12-15

### Added
- `getBaseSelect()` method to support bound params in base select query.

### Changed
- `propertiesToColumns()` now optionally allows conversions with a partial column map.
This makes it possible to generate secondary filters using a subset of properties.


## [2.2.2] - 2021-08-15

### Changed
- Internal refactoring and static analysis improvements.


## [2.2.1] - 2021-02-22

### Changed
- Specified additional types and enabled Psalm static analysis.
- PHP 7.4+ is now required.


## [2.2.0] Primordial Refinement - 2020-03-22

### Added
- `getOriginalFilter()` method to `QueryOptions` for retrieving the unprocessed filter array.


## [2.1.0] Benevolent Mystique - 2019-08-05

### Added
- `processRow()` method to alter a row directly before it is inserted or
updated. Useful for setting columns that aren't in the property map.

### Changed
- PUT and PATCH requests now return the number of affected rows, and no longer produce an error
if no rows were affected (e.g. if the request didn't change the value of any property).
- Excluded additional test files from production bundle.


## [2.0.0] Pressurized Arrangement - 2019-03-22

### Added
- `$sort` parameter to `getEntitiesByIds()`.

### Changed
- By default, results are now ordered by the ID field.
- `$fields` is now the second parameter of `getEntities()` instead of the last.

### Removed
- Previously deprecated `getBaseSelect()`, `getIdColumn()`, `getSelectId()`,
and `rowsToJson()` methods.


## [1.2.2] Pasteurized Recognition - 2019-03-04

### Fixed
- `getById()` route handler now respects `fields` parameter and only selects
the specified properties.


## [1.2.1] Reliant Progenitor - 2019-02-24

### Added
- Support for specifying dependent fields along with `getValue()` function.
Dependents of requested fields that weren't explicitly requested, or
aren't default, will still be selected but not output.

### Fixed
- Properly check `nullGroup` properties nested multiple levels deep. For example,
when selecting the field `a.b.c`, if `a` is a nullable group it will now be checked and
set to null as expected. Previously only the direct parent of a selected field was checked.


## [1.2.0] Emancipation Propagation - 2019-02-22

### Added
- Default and maximum limit can now be configured for each search route.
If not configured, these are set to 25 and 1000, respectively.
- `getBaseQuery()` method, which has an `$options` parameter that an instance of `QueryOptions`
is passed to. This makes it easy to only select specified field columns, and optionally
customize the query based on which fields are selected or particular filter/sort values.
- `getPropMap()` method, which allows merging additional property info with `getSelectMap()`.
For example, a type or value getter function can be specified to determine how the property is output.
- Optional `fields` parameter on `getEntityById()`, `getEntitiesByIds()`, and `getEntities()` methods.
Takes an array of strings specifying which fields to select.
If left empty all default properties will be selected.

### Deprecated
- `getBaseSelect()` - use `getBaseQuery()` instead.
- `rowsToJson()` - mapped property information is now used to automatically output selected fields.
- `getIdColumn()` and `getSelectId()` - set an `id` property in `getSelectMap()` or `getPropMap()`
instead. If the select query uses a different ID column name than the table used for
inserts/updates/deletes, override the table's ID column name by setting an `id` property in `getMap()`.


## [1.1.1] Maximal Limitation - 2019-01-16

### Fixed
- Error when requesting the maximum page size of 1000.

### Changed
- Upgraded peachy-sql dependency to v6.0.


## [1.1.0] Ambiguous Identity - 2019-01-11

### Added
- `getSelectId()` method to optionally override the column used to get entities by ID.
Necessary when a joined table has a column with the same name as the ID column.
- Return `offset`, `limit`, and `lastPage` properties from search route handler.
This makes it easy for API clients to see if there are more results to request.


## [1.0.2] Optimal Fixture - 2017-05-16

### Changed
- Methods for retrieving, patching, and deleting entities by IDs now
return early if passed an empty IDs array.


## [1.0.1] Exacting Characteristic - 2017-03-14

### Changed
- `RouteHandler` now ensures that search parameters have the correct type.


## [1.0.0] Cosmic Luminary - 2017-03-09

### Changed
- Initial stable release


[4.1.0]: https://github.com/devtheorem/phaster/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/devtheorem/phaster/compare/v3.0.0...v4.0.0
[3.0.0]: https://github.com/devtheorem/phaster/compare/v2.9.0...v3.0.0
[2.9.0]: https://github.com/devtheorem/phaster/compare/v2.8.0...v2.9.0
[2.8.0]: https://github.com/devtheorem/phaster/compare/v2.7.0...v2.8.0
[2.7.0]: https://github.com/devtheorem/phaster/compare/v2.6.0...v2.7.0
[2.6.0]: https://github.com/devtheorem/phaster/compare/v2.5.0...v2.6.0
[2.5.0]: https://github.com/devtheorem/phaster/compare/v2.4.0...v2.5.0
[2.4.0]: https://github.com/devtheorem/phaster/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/devtheorem/phaster/compare/v2.2.2...v2.3.0
[2.2.2]: https://github.com/devtheorem/phaster/compare/v2.2.1...v2.2.2
[2.2.1]: https://github.com/devtheorem/phaster/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/devtheorem/phaster/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/devtheorem/phaster/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/devtheorem/phaster/compare/v1.2.2...v2.0.0
[1.2.2]: https://github.com/devtheorem/phaster/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/devtheorem/phaster/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/devtheorem/phaster/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/devtheorem/phaster/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/devtheorem/phaster/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/devtheorem/phaster/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/devtheorem/phaster/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/devtheorem/phaster/tree/v1.0.0
