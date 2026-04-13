# Changelog

All notable changes to this project will be documented in this file.

## [1.1.1] - 2026-04-13
- **UI Refinement**: Made human-readable field labels bold in the dashboard for better visual separation.

## [1.1.0] - 2026-04-13
- **Feature**: Implemented Human-Readable Field Labels and Option Value resolution. 
    - Field slugs (e.g., `radio-1`) are now mapped to their readable labels (e.g., `Age Group`) in both Settings and Dashboard.
    - Internal option values (e.g., `one`/`two`) are now automatically resolved to human-readable names (e.g., `Male`/`Female`) in the entry table.
    - Settings checkboxes now display labels with technical keys in brackets for better clarity.

## [1.0.29] - 2026-04-13
- **Enhancement**: Implemented dynamic field detection that reads directly from Forminator configuration. This ensures fields are available for configuration even when a form has zero submissions.
- **Fix**: Filtered out internal Forminator meta keys (prefixed with `_`) and non-input field types from settings.

## [1.0.28] - 2026-04-12
- **Critical Fix**: Restored missing `update_wsm_data` method and synchronized `count_total_new_entries` to resolve PHP Fatal Errors during bulk updates.

## [1.0.27] - 2026-04-12
- **Enhancement**: Gated "Global Settings" in the admin UI to only appear when `WSM_LEGACY_ENABLED` is active.
- **Maintenance**: Synchronized version numbering across all files.

## [1.0.26] - 2026-04-12
- **Rebranding**: Officially changed the plugin name to **Forminator Submissions Manager**.
- **Enhancement**: Improved error reporting for legacy CSV imports with descriptive alerts.
- **Fix**: Resolved issue where legacy system could be disabled by a missing constant.
- **Security**: Added robust validation for column mapping and file uploads.

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
