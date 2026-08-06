# Absence calendar and Nextcloud out-of-office design

## Scope

Employees exposes one read-only Nextcloud app calendar named `Time off`
(`Отпуска` in Russian). For the authenticated principal it contains fully
approved absence records belonging to that employee and to employees whose
`id_manager` or `id_partner` hierarchy descends from that principal. The
closure is recursive, so “all subordinates” includes every lower level, not
only direct reports. The date-range filter is the only time restriction, so
historical vacations, sick leave and other approved non-attendance remain
visible when browsing past months.

## Calendar data flow

`EmployeeAbsenceCalendarProvider` derives the user id exclusively from the DAV
principal. `AbsenceHistoryMapper::findApprovedVisibleToUser()` computes the
self-or-descendant user set from the server-side employee hierarchy, applies
that set in SQL and returns the employee uid
and display name with each row. The calendar converts every row to a Sabre
`VEVENT` node, as required by Nextcloud's AppCalendar bridge. It never returns
the nested REST-style event envelope that caused Calendar to spin and log an
`Array to string conversion` followed by a Sabre type error.

## Out-of-office data flow

`OutOfOfficeSyncService` reconciles fully approved Vacation and Sick leave
records with the native DAV `AbsenceService`. For each employee it selects the
currently active matching record, otherwise the nearest future record. Native
Nextcloud events then schedule the start and end, set/revert the user status,
and notify Mail. The employee's manager is used as replacement contact when it
is a valid Nextcloud user.

The service stores the source record and exact managed fields in an Employees
tracking table. It clears native out-of-office data only when it still matches
the Employees-managed value, so a later manual user override is not deleted.
Approval, edit, rejection and cancellation trigger focused reconciliation;
a timed job repairs drift and advances to the next approved period.

## Mail integration

Personal Aonius Mail accounts may follow the system out-of-office setting via
Mail's public services and Dovecot ManageSieve. Shared mailboxes are excluded:
only an account whose address equals the user's Nextcloud email address or the
canonical `<uid>@aonius.ai` address is eligible. Existing IMAP credentials are
reused by Mail; no password is copied or logged. If Mail or ManageSieve is not
available, status synchronization continues and the failure is logged without
blocking the HR workflow.

## Verification

Contract and unit tests cover DAV event shape, visibility SQL, classification,
managed-clear safety and lifecycle hooks. TEST acceptance additionally checks
the real Calendar UI, historical events, Nextcloud logs, native DAV absence,
user status scheduling and a bounded ManageSieve autoresponder canary.
