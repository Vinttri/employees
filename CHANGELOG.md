# Changelog

## [v2.5.13] - 2026-08-05

### Fixed
- Use PostgreSQL-safe lowercase physical identifiers in database mappers while preserving legacy response keys.

### Changed
- Rename the user-facing application title to Employees.
- Provide complete English and Russian localization catalogues selected through the Nextcloud user language.
- Point project metadata to the maintained Vinttri fork.

### Verification
- `LOCALIZATION_CONTRACT_OK keys=1698 russian=1695`
- `POSTGRESQL_IDENTIFIER_CONTRACT_OK mappers=41`
- `CODEX_MAX_SELF_REVIEW_PASS`

## [v1.0.0-beta] - 2025-05-08

### Added
- Initial public beta release
- Employee management (CRUD)
- Team management
- Positions and roles
- Absences and vacation tracking
- File management linked to employees
- Admin panel for HR
