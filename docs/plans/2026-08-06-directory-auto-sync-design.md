# Nextcloud directory synchronization

## Required behaviour

Employees treats Nextcloud as a source of new directory records, not as the
owner of records after they have been created locally.

- Enabled Nextcloud users create missing employee records.
- Contacts `ORG` paths create missing department hierarchy nodes.
- Contacts `TITLE`/`ROLE` values create missing positions.
- Nextcloud Teams (including Collectives-backed teams) and department leaves
  create missing teams.
- Contacts manager metadata creates a reporting line only when both employees
  can be resolved without ambiguity.
- Existing Employees records are never overwritten by synchronization.
- A locally renamed or reassigned record remains unchanged on later runs.
- Deleting a synchronized record creates a tombstone. The same source record
  is not recreated by a later run.
- Missing or ambiguous source data is reported as requiring completion; the
  synchronizer does not guess.

## Model

`employee_directory_sync` stores source provenance independently from the HR
tables. A row maps an entity type and stable source key to a local integer id.
For reporting relationships it also stores the dependent employee id.

The mapping makes a local rename stable: subsequent runs find the mapping and
leave the local row untouched. If the mapped local row has been deleted, the
mapping is converted to a tombstone (`suppressed = true`). Explicit deletes do
the same immediately.

## Execution

`DirectorySyncService` is the only writer for automatic synchronization. It is
called by:

1. an administrator-facing **Synchronize now** action;
2. a Nextcloud timed job every 15 minutes.

The configured Employees data manager owns the Contacts address book used by
background synchronization. Sync status and the last result are written to
`employee_settings` for the administration UI.

## Safety and idempotency

Every entity follows the same insert-only state machine:

1. suppressed mapping: skip;
2. live mapping: keep the local record unchanged;
3. stale mapping: suppress it and skip;
4. matching manual record: adopt it without modifying it;
5. no match: create and bind it.

A second run with unchanged Nextcloud data must create zero records. Tests
cover create, rename, delete, and resynchronize behaviour.
