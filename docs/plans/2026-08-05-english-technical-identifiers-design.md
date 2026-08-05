# English Technical Identifiers Design

## Decision

Replace the empty `empleados` installation with a new Nextcloud application whose technical ID is
`employees`. The installed directory, application routes, PHP namespace, compiled asset prefixes,
configuration scope and navigation ID all use `employees`. The localized user-facing name remains
`Employees` in English and `Сотрудники` in Russian, selected by the current user's Nextcloud locale.

The current application tables contain zero rows. Consequently, the release uses a clean English
schema instead of retaining Spanish compatibility aliases. Every application-owned table, column,
index and constraint is defined with an English identifier. Runtime mappers and queries address only
that schema. Existing Spanish tables are removed only after a verified backup and successful install
of the replacement; a failure restores the previous application tree and database recovery point.

All executable source filenames and PHP class names are translated to English. Controller names,
route actions, services, entities, mappers, jobs, commands, notifications and frontend source paths
follow the same vocabulary. User-visible text remains in the native Nextcloud localization catalog;
translation keys may contain human language, but executable identifiers may not contain the retired
Spanish vocabulary.

## Verification

A source contract rejects `empleados` as an app ID/path/namespace/asset prefix and rejects the
defined Spanish vocabulary in executable filenames, PHP declarations and physical database schema
identifiers. PHP lint, Composer autoload validation, frontend lint/tests and a production build run
before packaging. Deployment requires an atomic backup, Nextcloud install/upgrade success, an exact
schema inventory showing only English application tables and columns, authenticated English/Russian
browser smoke, endpoint HTTP 200, clean application logs and a rollback receipt.
