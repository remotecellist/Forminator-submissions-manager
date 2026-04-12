# Forminator Submissions Manager

**Forminator Submissions Manager** is a lightweight yet powerful workflow extension for the Forminator WordPress plugin. It transforms standard form entries into a manageable tracking system, perfect for lead management, customer support, and administrative workflows.

---

## 🚀 Key Features

### 📋 Precision Workflow Tracking
- Add custom **Workflow Statuses** (e.g., New, Processing, Completed) to every submission.
- Maintain **Internal Notes** for each entry to track communication or internal tasks.
- Dedicated modern dashboard for individual or bulk entry management.

### ⚡ Instant AJAX Search & Filtering
- Experience "instant" results with our **Debounced AJAX Search** logic.
- **Loose Matching**: Search smarter with space-insensitive matching (e.g., searching "JohnDoe" finds "John Doe").
- Quick-filter entries by status using a premium, tabbed UI.

### 🛡️ Smart Duplication Detection
- Automatically detect duplicate submissions based on specific form fields.
- Visual badges in the dashboard identify original entries vs. duplicates.

### 📂 Legacy Data Integration
- Import historical data via CSV and link it to active Forminator forms.
- **Phone Normalization**: Advanced logic filters and matches phone numbers across different formats (e.g., with or without country codes).

### 📱 Premium Responsive Design
- Fully optimized administrative interface using a modern **Card-Based Mobile Layout**.
- Smooth transitions and interactive elements using **Event Delegation** for maximum reliability.

---

## 🛠️ Installation

1. Upload the plugin files to the `/wp-content/plugins/forminator-submissions-manager` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Submissions -> Settings** to select and configure the Forminator forms you want to track.

---

## 📦 Recent Changes

### [1.0.28] - 2026-04-12
- **Hotfix**: Resolved PHP Fatal Errors by restoring accidentally removed data methods and synchronizing internal naming.

### [1.0.27] - 2026-04-12
- **Enhancement**: Gated "Global Settings" in the admin dashboard to only appear when `WSM_LEGACY_ENABLED` is active.
- **Maintenance**: Incremented version number to 1.0.27.

### [1.0.26] - 2026-04-12
- **Rebranding**: Officially changed the plugin name to **Forminator Submissions Manager**.
- **Error Handling**: Added descriptive alerts and server-side validation for legacy imports.
- **Security**: Hardened legacy import system with improved mapping protection and CSV validation.

### [1.0.25] - 2026-04-11
- **Instant Search**: Implemented a debounced AJAX search for a fast, modern feel.
- **Loose Matching**: Improved search accuracy using space-insensitive comparison logic.
- **Event Delegation**: Refactored dashboard interactions for improved reliability after AJAX updates.

---

<<<<<<< HEAD
*For the full history of changes, please refer to the [CHANGELOG.md]*
=======
*For the full history of changes, please refer to the [CHANGELOG.md] file.*
>>>>>>> cd22d9cbe0eec642ed29e3237853ddbac8da8b45

## ⚖️ License
GPLv2 or later
