# Contextual field help

## Goal

Every data-entry control in Employees should explain itself without turning each form into documentation. A compact sentence is visible next to the field. A circular question-mark button opens a longer explanation containing both the reason the value is collected and a realistic example.

## Design

The application uses one progressive-enhancement layer for all Vue forms. It detects native inputs and Nextcloud select/text components after every render, derives the visible field label, and resolves help content through a central semantic catalogue. Specific concepts such as salary, IBAN, tax, absence, department, inventory and dates receive domain-aware explanations. Unknown controls receive a safe type-aware fallback, so a newly added field is never left without help.

Existing `HelpHint` components remain authoritative and are not duplicated. Those fields receive only the compact sentence. All generated controls use native buttons, `aria-describedby`, `role="tooltip"`, keyboard focus, Escape-to-close and click-to-pin for touch devices. Tooltips are positioned against the viewport and use only Nextcloud theme variables.

The enhancer runs in both the main Employees application and its administration settings. A MutationObserver covers dialogs, conditional tabs and dynamically added table rows. It never changes submitted values or application data.

## Verification

- Static coverage verifies that both entry points start the enhancer and that all supported control families are included.
- The production build must pass ESLint and Stylelint without new errors.
- Browser acceptance checks payroll, employee administration and at least one modal in Russian, then repeats a representative field in English.
- Every help button must work with hover, keyboard focus and click, and no Employees JavaScript error may be present.
