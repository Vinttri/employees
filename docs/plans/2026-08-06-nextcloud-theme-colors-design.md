# Nextcloud-native color design

## Decision

Employees does not own a visual color palette. Every visible color must come
from a semantic Nextcloud theme custom property. This includes surfaces, text,
borders, controls, status states, charts, overlays, shadows, print views, and
calendar entries. Component-specific ERP, sidebar, absence, and report palettes
are removed.

## Semantic mapping

- Surfaces: `--color-main-background`, `--color-background-hover`,
  `--color-background-dark`, and `--color-background-darker`.
- Text: `--color-main-text` and `--color-text-maxcontrast`.
- Accent: `--color-primary-element`, `--color-primary-element-hover`,
  `--color-primary-element-light`, and the matching text variables.
- State: Nextcloud success, warning, error, and info variables.
- Borders and shadows: `--color-border*` and `--color-box-shadow`.

Opacity may be applied with relative-color syntax, but its source must remain a
Nextcloud variable. There are no literal hex, RGB, HSL, or named palette colors
in application source.

## Runtime colors

Canvas-based charts cannot resolve CSS custom-property strings themselves. A
small utility resolves a Nextcloud custom property from the document root and
returns the current computed value. It can add alpha to an RGB value derived
from a Nextcloud variable. The utility never supplies a fallback palette.

## Verification

A frontend contract test scans all UI source files and rejects hardcoded color
literals or color fallbacks. The production build and existing frontend/PHP
suite must remain green. Final acceptance uses the signed-in TEST interface and
checks computed colors, screenshots, console errors, and affected server logs.
