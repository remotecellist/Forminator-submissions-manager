# Web Submissions Manager

Manage and track Forminator form submissions with custom status and notes.

## Description
Web Submissions Manager allows you to extend Forminator by adding custom workflow statuses and internal notes to each submission. It provides a dedicated dashboard where entries can be managed, searched, and exported.

## Installation
1. Upload the plugin files to the `/wp-content/plugins/web-submissions-manager` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to 'Submissions -> Settings' to select which Forminator forms you want to track.

## Changelog

### 1.0.24
- Enhancement: Optimized Dashboard for mobile responsiveness. Implemented a card-based layout for submission entries on smaller screens, ensuring full readability without horizontal scrolling.

### 1.0.23
- Enhancement: Major Dashboard UI modernization. Replaced basic form links with a premium tabbed interface and converted status filters into interactive, color-coded pills.

### 1.0.22
- Enhancement: Gated legacy management features (Import UI, Settings columns) behind a `WSM_LEGACY_ENABLED` constant for cleaner interface management. Dashboard matching remains active if data exists.

### 1.0.21
- Security Fix: Added frontend and API-level guards to prevent non-CSV files from triggering background extraction processes.

### 1.0.20
- Security Hardening: Implemented strict capability checks for background maintenance tasks.
- Security Hardening: Enhanced CSV import validation with MIME type and structural verification.
- Security Hardening: Improved database transaction safety for legacy data imports with automatic rollbacks on failure.
- UI Refinement: Restricted sensitive database information exposure in admin notices.

### 1.0.19
- Enhancement: Completely reinvented the mobile responsiveness of the core Settings table. It now collapses into a native, user-friendly CSS-card view under 782px wide instead of relying on basic horizontal scrolling.

### 1.0.18
- Responsive Layout Fix: Removed strict `min-width` parameters from Settings Page flex containers that blocked the table from activating horizontal scrolling on small screens.

### 1.0.17
- UI Fix: Adjusted the Legacy Records imported badge on the Settings page to use the correct background class to ensure the imported count is perfectly readable.

### 1.0.16
- Bug Fix: Resolved a JavaScript `TypeError` in `admin.js` preventing some script functionality on the settings page due to unavailable DOM elements.
- Layout Fix: Wrapped the settings table in a responsive container (`overflow-x: auto`) for better scaling on small viewports and avoiding horizontal overflows.

### 1.0.15
- Layout Fix: Stripped inline flex properties overriding layout definitions.
- UI Refinement: Restored the `wsm-settings-table` wrapper to fix structural column overlaps on the settings page.

### 1.0.14
- Layout Fix: Resolved column overlap by consolidating duplication settings into a single cell.
- Refined settings table column widths for better responsiveness.

### 1.0.13
- UI Refinement: Optimized settings table layout, column widths, and spacing.
- Improved Duplication Settings with a more compact grid layout.
- Streamlined Legacy Data mapping UI and moved global settings to a dedicated section.

### 1.0.12
- UI Modernization: Refined admin interface with a premium technical aesthetic.
- Enhanced card-based layouts for settings and improved dashboard badge styling.
- Responsive design improvements for mobile administrators.

### 1.0.11
- Maintenance: Forced database re-check and migration.

### 1.0.10
- Fixed: `wsm_legacy_data` table creation is now more robust with existence checks.
- Fixed: Double-prefix phone normalization (e.g. `4407...` becomes `447...`).
- Feature: One-time migration to re-normalize all legacy data with the improved logic.

### 1.0.9
- Fixed: Improved phone normalization to handle numbers missing leading zeros (common in CSV exports).
- Added: "Local Number Length" setting to control automatic country code prepending.
- Feature: Automatic background migration of existing legacy data on update.

### 1.0.8
- **Feature**: Added Legacy Data Integration with CSV import support.
- **Feature**: Selective phone normalization utility for accurate matching.
- **UI**: Triple-column mapping (Legacy UID, Match Value, Forminator Field).
- **UI**: Added bulk legacy match badges (`Legacy: #ID`) in dashboard.
- **Settings**: Added "Default Country Code" global setting.

### 1.0.7
- **Refactor**: Modularized codebase into separate classes for better maintainability.
- **Refactor**: Moved CSS and JavaScript to external files with versioning.
- **Documentation**: Added `README.md` and official changelog.

### 1.0.6
- Minor UI improvements.

### 1.0.1
- Initial release with status tracking and dashboard features.

## License
License: GPLv2 or later
