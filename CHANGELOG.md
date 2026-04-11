# Changelog

All notable changes to this project will be documented in this file.

## [1.0.26] - 2026-04-11
- Rebranded plugin to **Forminator Submissions Manager**.
- Updated internal UI titles and metadata for a more unified Forminator experience.

## [1.0.25] - 2026-04-11
- Feature: Implemented Instant AJAX Search with 400ms debounce.
- Enhancement: Added "Loose Matching" logic (space-insensitive) to search functionality.
- Refactor: Implemented Event Delegation architecture for the dashboard table.
- UI: Added loading spinners and row selection highlights.

## [1.0.24] - 2026-04-10
- Enhancement: Optimized Dashboard for mobile responsiveness with card-based layout.

## [1.0.23] - 2026-04-10
- Enhancement: Tabbed interface for form selection and color-coded status pills.

## [1.0.22] - 2026-04-10
- Enhancement: Gated legacy management features behind `WSM_LEGACY_ENABLED`.

## [1.0.21] - 2026-04-10
- Security Fix: Added guards for non-CSV files in maintenance tasks.

## [1.0.20] - 2026-04-10
- Security Hardening: Strict capability checks for background tasks.
- Security Hardening: Enhanced CSV import validation (MIME/structural).
- Security Hardening: Database transaction safety for legacy imports.

## [1.0.19] - 2026-04-10
- Enhancement: Card-based responsive view for the Settings table on mobile.

## [1.0.18] - 2026-04-10
- Fixed: Settings Page flex container scaling issues on small screens.

## [1.0.10] - 2026-04-09
- Feature: Improved phone normalization with double-prefix handling.
- Feature: Background migration for existing legacy data.

## [1.0.8] - 2026-04-01
- Feature: Initial Legacy Data Integration.
- Feature: Bulk legacy matching in dashboard.

## [1.0.1] - 2026-03-20
- Initial release with status tracking and dashboard features.
