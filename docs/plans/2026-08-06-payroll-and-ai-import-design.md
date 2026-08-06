# Payroll and universal AI import design

## Outcome

Employees gains a country-neutral payroll module and a reusable AI-assisted import flow. Payroll computes gross and net amounts from explicit, reviewable rules. Tax legislation is not hard-coded: administrators create multiple tax, discount, earning, and deduction profiles and assign them to employees with effective dates.

Every AI import is a draft. Uploaded CSV, Markdown, plain text, or pasted text is parsed by the Nextcloud TaskProcessing provider into a target schema, validated again by Employees, matched to records the actor may see, and displayed for human review. No AI result writes directly to business tables.

## Payroll model

An employee keeps the existing monthly base salary. A compensation plan adds payment mode, currency, hourly rate, internal cost rate, standard monthly hours, overtime rate, and effective dates. Supported modes are monthly salary, hourly, monthly plus hours, fixed period, commission, and piecework.

Reusable rule profiles contain ordered earning and deduction rules. A rule can be a fixed amount, a percentage of base salary, a percentage of gross earnings, or an amount per hour/unit. Employee assignments may override rule values and model individual Cyprus discounts without embedding Cyprus law.

A payroll period contains immutable payslip snapshots. Inputs for a period include approved internal time reports, imported hours, manually entered hours, commissions, bonuses, reimbursements, taxes, deductions, and one-off payments. Drafts may be recalculated; approved periods are corrected by additional adjustment lines rather than rewriting history. Payment batches record paid amounts and references independently from accruals.

All monetary columns use NUMERIC(18,2), quantities NUMERIC(12,4), dates DATE, timestamps DATETIME, booleans BOOLEAN, and primary/foreign keys INTEGER. Currency is ISO 4217 text. Calculations use decimal strings and integer minor units at boundaries, never binary floating-point arithmetic.

## AI import platform

The schema registry describes each target's fields, types, required values, enums, permissions, and resolver lists. Targets initially cover employees, departments, positions, teams, time entries, absences, payroll plans, payroll inputs, payments, clients, activities, costs, purchases, inventory, and maintenance.

The server schedules `core:text2text:chat` through `OCP\TaskProcessing\IManager`. The system prompt treats uploaded content as untrusted data and requires JSON only. Results pass strict JSON parsing, field allowlists, type validation, record resolution, authorization, and duplicate detection. Exact UID, employee number, or email matches are automatic; fuzzy name matches remain review-required.

Import batches store the target, actor, task ID, source hash, statuses, validation messages, proposed rows, and applied record references. Raw source content is temporary and is removed after completion. Applying a batch is transactional and idempotent. The UI shows ready, review, and invalid rows and permits edits before confirmation.

## Interface

Payroll uses a restrained, native Nextcloud layout with no hard-coded brand colors. The overview has one period selector, totals for gross, deductions, net, and payment state, followed by a dense employee table. A right-side detail panel edits one payslip without moving the page header. Compensation plans and rule profiles live under Payroll settings.

Every supported manual-entry screen receives the same `AI import` action. The reusable dialog accepts drag-and-drop files or pasted text, shows task progress without an endless spinner, then renders an editable preview table. Components use `@nextcloud/vue`, Nextcloud CSS variables, native typography, visible focus, and responsive layouts.

## Permissions, audit, and safety

Payroll view, manage, approve, pay, export, and AI-import permissions are separate and enforced server-side. Employees may view only their own approved payslips. Managers do not receive salary access merely because they manage a team.

Every plan change, calculation, approval, payment, export, and import application writes an audit event without storing provider secrets or unnecessary personal source text. Exports are generated only from approved or paid snapshots and include CSV for detailed data plus a bank-neutral payment CSV. Country-specific bank formats remain adapters.

## Verification

Tests cover all calculation modes, effective-dated profiles, multiple discounts, rounding, duplicate imports, ambiguous employees, invalid AI output, authorization, immutable approvals, exports, and database type contracts. TEST acceptance requires migration rollback backup, app upgrade, seeded examples, a real native-provider import, calculation and export comparison, Russian/English UI checks, console/network error review, and mobile/desktop screenshots.
