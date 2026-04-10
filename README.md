# Web Submissions Manager

Manage and track Forminator form submissions with custom status and notes.

## Description
Web Submissions Manager allows you to extend Forminator by adding custom workflow statuses and internal notes to each submission. It provides a dedicated dashboard where entries can be managed, searched, and exported.

## Installation
1. Upload the plugin files to the `/wp-content/plugins/web-submissions-manager` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to 'Submissions -> Settings' to select which Forminator forms you want to track.

## Changelog

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
