# Monthly payroll workflow

## Goal

Turn the existing payroll tables into one employee-centred monthly workflow that an HR or finance manager can understand without knowing the underlying schema.

The result of an approved payroll period is not only a downloadable response. It is a persistent, auditable package of files stored in the Employees Team Folder and visible in a month-by-month file browser.

## User workflow

1. Configure each employee once: payment mode, monthly salary, hourly and overtime rates, standard monthly hours, currency, tax profile, personal tax adjustments and IBAN.
2. Create a month. For hourly and mixed plans, recorded Employees time reports are used automatically. A manual or imported hours entry overrides the automatic total for that employee and month.
3. Add bonuses, one-off payments, reimbursements, deductions or tax adjustments, then calculate the whole period.
4. Review every employee, gross pay, deductions, net pay and validation warnings. Approval is blocked while an active employee has no valid plan or an invalid bank account required for the bank package.
5. Approve the period. The calculation becomes immutable and the application generates the period file package.
6. Download the same package from the interface or open it in the shared month-by-month file browser. Record payments after the bank transfer is submitted.

## Interface

The main Payroll page is a four-step guided workspace:

- **Employee setup** — readiness list and a single editor per employee, with advanced tax rules collapsed by default.
- **Hours and adjustments** — automatic/manual source is explicit for every employee; the global AI Import screen remains the only AI import entry point.
- **Review and approve** — calculation totals, warnings and employee rows.
- **Bank and documents** — the current period package and a folder-like list of payroll months.

Technical plan, tax profile and rule tables remain available inside an Advanced section for administrators, but they are not the primary workflow.

The UI uses only Nextcloud design tokens and components. It has no hard-coded product colours.

## Persistent file layout

The data manager's Team Folder is the source of truth:

```text
Employees_storage/
  Payroll/
    Calculations/
      YYYY-MM/
        Payroll register - YYYY-MM.csv
        SEPA credit transfer - YYYY-MM.xml
        Payslips/
          Payslip - YYYY-MM - Employee Name.pdf
  user.uid - EMPLOYEE NAME/
    Official documents/
      Payroll/
        Payslip - YYYY-MM - Employee Name.pdf
```

Approval first commits the immutable database state, then publishes an idempotent package. Repeating publication replaces deterministic filenames rather than creating duplicates. Publishing errors are recorded per period and payslip and can be retried without recalculation.

## Bank outputs

Both formats are generated from approved, positive outstanding amounts:

- Excel-friendly UTF-8 CSV with BOM, `sep=;`, semicolon delimiter, localized human-readable column names and spreadsheet-formula injection protection.
- SEPA Credit Transfer XML using ISO 20022 `pain.001.001.09`, with one transaction per employee. It is restricted to EUR and validates debtor settings and employee IBANs before creation.

Company/debtor name, IBAN, BIC and payment reference template are app settings. Employee `number_account` is treated as the beneficiary IBAN.

## Payslips

Each approved result produces a localized PDF using the employee's Nextcloud language. It includes period, employee, plan, hours source, earnings, deductions, gross, paid and net amounts. The same bytes are written to the shared period package and the employee's own document folder.

## Data and API changes

- Add document/package publication state, path, file id, timestamp and last error to payroll periods and payslips.
- Add an employee payroll setup endpoint which creates a new effective-dated plan when the current plan is locked and idempotently assigns the selected tax profile.
- Add bank-settings endpoints.
- Add publish/retry, file listing, CSV, SEPA and payslip PDF endpoints.
- Preserve existing lower-level endpoints for compatibility and AI import.

All identifiers and foreign keys remain integers/bigints, monetary and rate values remain numeric decimals, dates remain date columns, timestamps remain datetime columns and paths/errors remain text strings. Migration and code contracts are tested together.

## Failure and rollback behaviour

- Calculation remains editable until approval.
- Approval never rolls back because file storage is temporarily unavailable; it records `pending` or `error` publication state and offers retry.
- Bank exports never silently omit an invalid employee. They return a row-level validation report and produce no SEPA file until all required data is valid.
- Generated files are deterministic, so retry is safe.
- Database rollback removes only the new publication metadata. Existing payroll plans, inputs, results and payments are preserved.
