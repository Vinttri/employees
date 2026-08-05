# Employees UI system hardening

## Problem

Editing is technically available in several modules but hidden in duplicated
overflow menus. Employee data is split between independent save buttons and
the Notes tab inverts the parent edit state. The app also mixes hard-coded
colors, radii, shadows and native controls, which makes the interface look and
behave differently from one route to another and from one Nextcloud theme to
another.

## Design direction

Keep the existing Nextcloud application and APIs, but add a shared,
Nextcloud-native interaction and visual layer:

- make the primary edit action visible in employee, department, position and
  team headers;
- show an explicit editing state with Save and Cancel actions;
- keep destructive and secondary actions in the overflow menu;
- use Nextcloud CSS variables for color, surface, border, focus and radius;
- normalize native inputs, cards, toolbars, tabs and modal forms without
  replacing maintained `@nextcloud/vue` components;
- make organization charts readable, scrollable and theme-aware instead of
  treating a successful data load as a usable graph.

## Interaction contract

1. A selected record opens in read mode.
2. A visible Edit button enables the relevant fields.
3. Save validates and persists the record, then refreshes the selected data.
4. Cancel restores the last server-backed values and leaves read mode.
5. A failed request leaves the form editable and presents the server message.
6. Notes follow the same edit state; preview is a display choice, not a second
   edit mode.

## Acceptance

- employee employment and personal fields survive save and reload;
- department, position and team edit modals open and save;
- create/import actions remain reachable and permission-aware;
- every main route opens without app JavaScript errors or HTTP 5xx;
- light and dark themes use one accent and consistent controls;
- organization charts remain readable at desktop and narrow widths;
- lint, build, PHP/backend tests and authenticated browser smoke tests pass.
