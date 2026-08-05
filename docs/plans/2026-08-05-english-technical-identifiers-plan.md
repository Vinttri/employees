# English Technical Identifiers Implementation Plan

1. Add a failing contract for the `employees` ID, namespace, source filenames, class names and schema.
2. Translate backend classes/files and update all PHP references and route controller/action names.
3. Replace legacy migrations with one clean English final-schema migration for the new app ID.
4. Translate mapper/query table and column identifiers and update frontend API routes and source paths.
5. Change build/package metadata and regenerate production assets under the `employees` prefix.
6. Run source, PHP, Composer, frontend and production-build gates.
7. Commit and push the application fork, then pin it in the owning NextCloud repository.
8. Back up the live tree/database, replace `employees` with `employees`, and run API/schema/browser/log smoke.
9. Merge both repositories only after the deployed source and pinned commit match exactly.
