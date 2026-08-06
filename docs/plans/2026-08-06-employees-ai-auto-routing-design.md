# Employees AI import: automatic row routing

## Goal

Make AI import a single, understandable workspace. Users upload or paste CSV,
Markdown, TXT, or free-form text without choosing a destination first. The
native Nextcloud AI provider classifies every extracted row, while the user
reviews the proposed destination and normalized fields before any write occurs.

Remove contextual AI-import buttons from feature pages. The only entry point is
the persistent **AI import** navigation item.

## Chosen approach

Use one reviewed import batch with target `auto`. The AI response contains one
object per proposed record with `_target`, `_source_row`, and the fields allowed
for that target. This supports mixed files in one task and keeps the existing
atomic review/apply workflow.

The server supplies only targets the current user may import. A returned target
outside that allow-list is invalid. Unknown or ambiguous rows are never applied.
The user may correct a proposed destination during review; server validation is
rerun against the newly selected schema before Apply becomes available.

Alternatives rejected:

- File-level classification cannot explain or route mixed rows.
- A classifier task followed by one extraction task per target adds latency,
  partial-failure states, and task orchestration without improving the review
  contract for the current data volumes.

## Data flow

1. `GET /ai-import/targets` returns the permission-filtered target schemas.
2. The UI posts the source to `POST /ai-import/auto`.
3. The server schedules one native Nextcloud `TextToTextChat` task. Its prompt
   lists the allowed target IDs and schemas and treats file content as untrusted.
4. The response is validated row by row. Each row retains `_target`, source-row
   metadata, normalized values, match metadata, messages, and status.
5. The review screen groups counts by destination and renders the destination,
   source row, editable fields, and validation messages for every proposed row.
6. Revalidate runs all edited rows through the server. Apply is enabled only
   when every row is ready.
7. Apply rechecks permissions and schemas inside one database transaction, then
   records each row through the existing target-specific service.

Legacy explicit-target batches remain readable and applicable for compatibility,
but the user interface no longer creates them.

## Interface

The source screen contains a concise capability map grouped as Organization,
Time and absences, Payroll, and Business and IT. It states that mixed content is
supported and that nothing is saved before confirmation.

The review screen starts with a routing summary: target name and row count. Each
row is a compact Nextcloud-native card with state, source-row number, destination
selector, target-specific field grid, and human-readable messages. No custom
brand colors are used: surfaces, borders, status colors, focus, spacing, and
controls use Nextcloud components and CSS tokens.

Apply copy explicitly reports how many rows will be written and to which
destinations.

## Failure and safety rules

- Missing, unavailable, or unauthorized `_target`: invalid row.
- Ambiguous employee match: review row.
- Unknown fields: review row and ignore unknown values.
- Required or typed-field failure: invalid row.
- Changing destination discards fields not present in the new schema on server
  revalidation; no mass assignment is possible.
- All writes remain transactional and require an explicit Apply action.
- Existing deduplication includes the `auto` target and source hash.

## Verification

- Unit tests for mixed-target validation, unauthorized/unknown destinations,
  destination correction, and legacy explicit-target behavior.
- Service tests/contracts for automatic prompt and per-row transactional apply.
- Frontend tests for no contextual import buttons, capability explanation,
  routing summary, destination per row, and no manual destination selector before
  analysis.
- Lint, type checks, production build, package test, TEST deployment, and real
  browser acceptance in Russian and English.
