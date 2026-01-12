# My-Uploads-Sentry 🛡️

**A lightweight, defensive security monitor for your WordPress Uploads directory.**

![Version](https://img.shields.io/badge/version-1.2.4-blue.svg)
![License](https://img.shields.io/badge/license-GPLv2-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-orange.svg)

## 📖 Description

**My-Uploads-Sentry** is a backend utility designed for system administrators and security-conscious developers. It implements a "Defense in Depth" strategy by monitoring the `wp-content/uploads` directory (and other whitelisted static folders) for executable files that shouldn't be there.

Unlike heavy WAF plugins, this tool focuses on a single task: **ensuring your static asset directories remain static.**

### Key Features

* **🛡️ Precision Monitoring:** Scans `uploads` and user-selected static directories for executable extensions (`.php`, `.exe`, `.sh`, etc.).
* **🧠 Smart Discovery:** Automatically detects and suggests safe directories to monitor while **hard-excluding** code-heavy directories (Plugins, Themes, Core) to prevent false positives.
* **🚀 Performance Optimized:** Uses WordPress Transients API to cache scan results, ensuring zero impact on admin dashboard performance.
* **📊 Real-time Dashboard Widget:** Provides immediate visual feedback (Green/Red status) directly on the WP Admin Dashboard.
* **📝 Audit Trail:** Records and displays the timestamp of the last successful scan.
* **🔒 Secure by Design:** Implements strict allow-list validation (OWASP A03 mitigation) to prevent path traversal attacks via settings.

## ⚙️ Requirements

* **WordPress:** 6.9 or higher
* **PHP:** 7.4 or higher
* **Permissions:** `manage_options` capability (Admins only)

## 📥 Installation

1.  Download the plugin `.zip` file.
2.  Go to your WordPress Admin Dashboard: **Plugins > Add New**.
3.  Click **Upload Plugin** and select the zip file.
4.  **Activate** the plugin.
5.  Check the **Dashboard** for the "My-Uploads-Sentry" widget.

## 🔧 Configuration

Go to **Settings > Uploads Sentry** to configure the plugin:

1.  **Scan Cache Duration:** Choose how long to cache results (Default: 1 Hour).
2.  **Monitor Scope:** Select additional directories to monitor.
    * *Note: The system automatically filters out `plugins`, `themes`, and `wp-admin` to ensure stability.*

## 📸 Screenshots

### 1. Dashboard Widget (Secure Status)
Displays a clean green status when no threats are found, complete with the last scan timestamp.
*(Add your screenshot here)*

### 2. Help Tooltip
Hover over the `(?)` icon to see the legend and scope details.
*(Add your screenshot here)*

### 3. Settings Page
Configure cache duration and select target directories securely.
*(Add your screenshot here)*

## 🧩 Technical Details

### Security Logic
The plugin utilizes a **"Strict Allow-list + Block-list"** mechanism:
1.  **Block-list:** Hardcoded exclusion of `wp-admin`, `wp-includes`, `plugins`, `themes`, `mu-plugins`, and `cache`.
2.  **Allow-list:** When saving settings, the plugin verifies that the submitted paths exist within the system-generated candidate list. Arbitrary path input (e.g., `../../etc/passwd`) is silently discarded.

### File Extensions Scanned
The scanner looks for the following extensions:
`php`, `php*`, `phtml`, `pl`, `py`, `cgi`, `asp`, `aspx`, `exe`, `sh`, `bash`, `cmd`

## 📋 Changelog

### 1.2.4
* **UX:** Added a pure CSS Help Tooltip `(?)` to the dashboard widget.
* **UI:** Refined widget layout and spacing.

### 1.2.3
* **Feature:** Added "Last Scan Timestamp" to the widget footer.
* **Fix:** Implemented timezone-aware date formatting using `wp_date()`.

### 1.2.2
* **Security:** Hardened settings sanitization to prevent Path Traversal (OWASP A03/A01).
* **Core:** Implemented strict allow-list validation logic.

### 1.2.0
* **Feature:** Introduced Settings API (Settings > Uploads Sentry).
* **Feature:** Added "Smart Directory Discovery" for configuring monitor scope.
* **Performance:** Added configurable cache duration.

## 📄 License

My-Uploads-Sentry is open-source software licensed under the **GPL v2 or later**.

---

**Author:** [WP 導盲犬](https://wp365.me/wp)
