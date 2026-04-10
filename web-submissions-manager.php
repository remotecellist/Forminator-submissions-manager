<?php
/**
 * Plugin Name: Web Submissions Manager
 * Description: Manage and track Forminator form submissions with custom status and notes.
 * Version: 1.0.7
 * Author: Syed Badar Abbas
 */

if (!defined('ABSPATH'))
    exit;

// ─── CONSTANTS ─────────────────────────────────────────────────────────────

define('WSM_VERSION', '1.0.7');
define('WSM_TABLE_ENTRIES', $GLOBALS['wpdb']->prefix . 'wsm_entries');
define('WSM_TABLE_FORMS', $GLOBALS['wpdb']->prefix . 'wsm_forms');
define('WSM_PATH', plugin_dir_path(__FILE__));
define('WSM_URL', plugin_dir_url(__FILE__));

// ─── INCLUDES ──────────────────────────────────────────────────────────────

require_once WSM_PATH . 'includes/class-wsm-data.php';
require_once WSM_PATH . 'includes/class-wsm-settings.php';
require_once WSM_PATH . 'includes/class-wsm-dashboard.php';
require_once WSM_PATH . 'includes/class-wsm-export.php';

// ─── INITIALIZATION ────────────────────────────────────────────────────────

class WebSubmissionsManager
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        register_activation_hook(__FILE__, [$this, 'install']);
        add_action('init', [$this, 'maybe_install']);
        add_action('admin_init', [$this, 'backfill_missing_entries']);
        add_action('forminator_custom_form_after_save_entry', [$this, 'submission_hook'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        new WSM_Settings();
        new WSM_Dashboard();
        new WSM_Export();
    }

    public function install()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS " . WSM_TABLE_ENTRIES . " (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id      BIGINT(20) UNSIGNED NOT NULL,
            entry_id     BIGINT(20) UNSIGNED NOT NULL,
            status       VARCHAR(50) NOT NULL DEFAULT 'New',
            notes        LONGTEXT,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by   BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY entry_id (entry_id)
        ) $charset;";

        $sql2 = "CREATE TABLE IF NOT EXISTS " . WSM_TABLE_FORMS . " (
            id       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id  BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY form_id (form_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        update_option('wsm_db_version', WSM_VERSION);
    }

    public function maybe_install()
    {
        if (get_option('wsm_db_version') !== WSM_VERSION) {
            $this->install();
        }
    }

    public function backfill_missing_entries()
    {
        if (!is_admin())
            return;
        if (get_transient('wsm_backfill_done'))
            return;

        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$entry_table'"))
            return;

        $tracked_ids = WSM_Data::get_tracked_form_ids();
        if (empty($tracked_ids))
            return;

        $user_id = get_current_user_id();

        foreach ($tracked_ids as $form_id) {
            $missing = $wpdb->get_col($wpdb->prepare(
                "SELECT e.entry_id
                 FROM {$entry_table} e
                 LEFT JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id
                 WHERE e.form_id = %d
                 AND w.entry_id IS NULL",
                $form_id
            ));

            foreach ($missing as $entry_id) {
                $wpdb->insert(
                    WSM_TABLE_ENTRIES,
                    [
                        'form_id' => $form_id,
                        'entry_id' => $entry_id,
                        'status' => 'New',
                        'notes' => '',
                        'updated_by' => $user_id,
                    ],
                    ['%d', '%d', '%s', '%s', '%d']
                );
            }
        }

        set_transient('wsm_backfill_done', 1, 12 * HOUR_IN_SECONDS);
    }

    public function submission_hook($form_id, $entry)
    {
        global $wpdb;

        $tracked_ids = WSM_Data::get_tracked_form_ids();
        if (!in_array((string) $form_id, array_map('strval', $tracked_ids)))
            return;

        $entry_id = is_object($entry) ? (int) $entry->entry_id : (int) $entry;
        if (!$entry_id)
            return;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . WSM_TABLE_ENTRIES . " WHERE entry_id = %d",
            $entry_id
        ));

        if (!$exists) {
            $wpdb->insert(
                WSM_TABLE_ENTRIES,
                [
                    'form_id' => $form_id,
                    'entry_id' => $entry_id,
                    'status' => 'New',
                    'notes' => '',
                    'updated_by' => 0,
                ],
                ['%d', '%d', '%s', '%s', '%d']
            );
        }
    }

    public function enqueue_assets()
    {
        $screen = get_current_screen();
        if (!strpos($screen->id, 'wsm-') && !strpos($screen->id, 'submissions'))
            return;

        wp_enqueue_style('wsm-admin-css', WSM_URL . 'assets/css/admin.css', [], WSM_VERSION);
        wp_enqueue_script('wsm-admin-js', WSM_URL . 'assets/js/admin.js', ['jquery'], WSM_VERSION, true);

        $active_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        if (!$active_form_id) {
            $tracked = WSM_Data::get_tracked_form_ids();
            if (!empty($tracked))
                $active_form_id = $tracked[0];
        }

        wp_localize_script('wsm-admin-js', 'wsmData', [
            'nonce' => wp_create_nonce('wsm_nonce'),
            'formId' => (string) $active_form_id,
            'exportNonce' => wp_create_nonce('wsm_export'),
            'statuses' => WSM_Data::get_statuses(),
            'statusColors' => WSM_Data::get_status_colors(),
        ]);
    }
}

WebSubmissionsManager::get_instance();