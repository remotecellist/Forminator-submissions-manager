<?php
if (!defined('ABSPATH'))
    exit;

class WSM_Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_wsm_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_wsm_save_order', [$this, 'save_order']);
        add_action('wp_ajax_wsm_import_legacy', [$this, 'import_legacy']);
        add_action('wp_ajax_wsm_clear_legacy', [$this, 'clear_legacy']);
        add_action('wp_ajax_wsm_get_csv_headers', [$this, 'get_csv_headers']);
    }

    public function add_menu()
    {
        $total_new = WSM_Data::get_total_new_entries();
        $menu_title = 'Submissions';
        if ($total_new > 0) {
            $menu_title .= ' <span class="update-plugins count-' . esc_attr($total_new) . '"><span class="plugin-count">' . esc_html(number_format_i18n($total_new)) . '</span></span>';
        }

        add_menu_page(
            'Web Submissions Manager',
            $menu_title,
            'manage_options',
            'wsm-dashboard',
            'wsm_page_dashboard', // This will be defined in class-wsm-dashboard.php or as a global if not careful
            'dashicons-feedback',
            30
        );
        add_submenu_page(
            'wsm-dashboard',
            'All Submissions',
            'All Submissions',
            'manage_options',
            'wsm-dashboard',
            'wsm_page_dashboard'
        );
        add_submenu_page(
            'wsm-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'wsm-settings',
            [$this, 'render_page']
        );
    }

    public function render_page()
    {
        global $wpdb;
        $all_forms = WSM_Data::get_forminator_forms();
        $tracked_ids = WSM_Data::get_tracked_form_ids();
        $dup_fields_map = get_option('wsm_duplicate_fields', []);
        $dup_form_ids = get_option('wsm_track_duplicate_forms', []);
        $default_cc = get_option('wsm_default_cc', '44');
        $local_len = get_option('wsm_local_number_length', '10');
        $legacy_config = get_option('wsm_legacy_config', []);
        ?>
                <div class="wrap wsm-wrap">
                    <h1>⚙️ Web Submissions Manager &mdash; Settings</h1>
                    <p>Select which Forminator forms to track. Drag tracked forms to reorder their tab appearance.</p>

                    <?php if (empty($all_forms)):
                        global $wpdb;
                        $all_tables = $wpdb->get_col("SHOW TABLES");
                        $fi_tables = array_filter($all_tables, fn($t) => stripos($t, 'frmt') !== false || stripos($t, 'forminator') !== false);
                        $fi_pts = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_type LIKE '%forminator%' OR post_type LIKE '%frm%'");
                        ?>
                            <div class="notice notice-warning">
                                <p><strong>No Forminator forms found.</strong></p>
                                <p><strong>DB tables with "frmt" or "forminator":</strong> <?php echo $fi_tables ? esc_html(implode(', ', $fi_tables)) : '<em>none</em>'; ?></p>
                                <p><strong>Post types in wp_posts matching "forminator" or "frm":</strong> <?php echo $fi_pts ? esc_html(implode(', ', $fi_pts)) : '<em>none</em>'; ?></p>
                                <p>Share the above with your developer to fix the query.</p>
                            </div>
                    <?php else: ?>
                        <div class="wsm-settings-flex-container">

                            <!-- Left: Select forms to track -->
                            <div class="wsm-settings-section wsm-section-forms">
                                <h3 style="margin-top:0">1. Select Forms to Track</h3>
                                <form id="wsm-settings-form">
                                    <div class="wsm-table-responsive" style="overflow-x: auto; width: 100%;">
                                        <table class="wsm-settings-table widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Form ID</th>
                                                    <th>Form Name</th>
                                                    <th>Track?</th>
                                                    <th>Duplication Settings</th>
                                                    <th>Legacy Data (CSV)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_forms as $form):
                                                    $dup_string = isset($dup_fields_map[$form->id]) ? $dup_fields_map[$form->id] : '';
                                                    $dup_arr = array_filter(array_map('trim', explode(',', $dup_string)));
                                                    $fields = WSM_Data::get_form_fields_for_settings($form->id);
                                                    $is_dup_tracked = in_array((string) $form->id, array_map('strval', $dup_form_ids));
                                                    ?>
                                                    <tr>
                                                        <td data-label="Form ID"><?php echo esc_html($form->id); ?></td>
                                                        <td data-label="Form Name"><?php echo esc_html($form->name); ?></td>
                                                        <td data-label="Track?">
                                                            <input type="checkbox" class="wsm-form-checkbox" name="form_ids[]"
                                                                value="<?php echo esc_attr($form->id); ?>"
                                                                <?php checked(in_array((string) $form->id, array_map('strval', $tracked_ids))); ?> />
                                                        </td>
                                                        <td data-label="Duplication Settings">
                                                            <div class="wsm-dup-control-wrapper" style="margin-bottom:10px;">
                                                                <label style="font-weight:700; display:flex; align-items:center; gap:8px;">
                                                                    <input type="checkbox" class="wsm-track-dups-checkbox" name="track_dup_forms[]"
                                                                        value="<?php echo esc_attr($form->id); ?>"
                                                                        <?php checked($is_dup_tracked); ?> />
                                                                    Track Duplications
                                                                </label>
                                                            </div>
                                                            <div class="wsm-duplicate-fields-container" style="<?php echo $is_dup_tracked ? '' : 'display:none;'; ?>">
                                                                <?php if (empty($fields)): ?>
                                                                        <em style="font-size: 11px; color: #666;">No entries found.</em>
                                                                        <input type="text" class="wsm-duplicate-fields-input wsm-input" name="duplicate_fields[<?php echo esc_attr($form->id); ?>]" value="<?php echo esc_attr($dup_string); ?>" placeholder="phone-1, text-1" style="width: 100%; margin-top: 4px;" />
                                                                <?php else: ?>
                                                                        <div class="wsm-duplicate-fields-options" data-fid="<?php echo esc_attr($form->id); ?>">
                                                                        <?php foreach ($fields as $fkey):
                                                                            $flabel = ucwords(str_replace(['-', '_'], ' ', $fkey));
                                                                            ?>
                                                                                <label class="wsm-field-option">
                                                                                    <input type="checkbox" class="wsm-dup-check" value="<?php echo esc_attr($fkey); ?>" <?php checked(in_array($fkey, $dup_arr)); ?>> 
                                                                                    <span class="wsm-field-label"><?php echo esc_html($flabel); ?></span>
                                                                                    <span class="wsm-field-key">(<?php echo esc_html($fkey); ?>)</span>
                                                                                </label>
                                                                        <?php endforeach; ?>
                                                                        </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td data-label="Legacy Data (CSV)">
                                                            <div class="wsm-legacy-container">
                                                                <?php
                                                                $has_legacy = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . WSM_TABLE_LEGACY . " WHERE form_id = %d", $form->id));
                                                                $config = $legacy_config[$form->id] ?? [];
                                                                ?>
                                                                <div class="wsm-legacy-status" style="margin-bottom:8px;">
                                                                    <?php if ($has_legacy): ?>
                                                                            <span class="wsm-legacy-badge-active">
                                                                                ✅ <?php echo number_format_i18n($has_legacy); ?> records imported
                                                                            </span>
                                                                            <button type="button" class="button button-link-delete wsm-clear-legacy" data-fid="<?php echo esc_attr($form->id); ?>" style="font-size:11px; margin-left:5px;">Clear & Re-import</button>
                                                                    <?php else: ?>
                                                                            <span style="font-size:12px; color:#666;">No legacy data.</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div class="wsm-legacy-import-ui" style="<?php echo $has_legacy ? 'display:none;' : ''; ?>">
                                                                    <input type="file" class="wsm-legacy-file" accept=".csv" style="font-size:11px; width:100%; margin-bottom:5px;">
                                                                        <div class="wsm-legacy-mapping" style="display:none; margin-top:10px; border-top:1px solid #e2e8f0; padding-top:12px;">
                                                                            <p style="margin:0 0 8px 0; font-size:11px; font-weight:700; text-transform:uppercase; color:var(--wsm-text-muted);">Step 2: Map CSV Columns</p>
                                                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                                                                                <label>Unique ID Col: 
                                                                                    <select class="wsm-csv-uid-col"></select>
                                                                                </label>
                                                                                <label>Match Col: 
                                                                                    <select class="wsm-csv-match-col"></select>
                                                                                </label>
                                                                                <label style="grid-column: span 2;">Forminator Field to Match: 
                                                                                    <select class="wsm-form-field-key" style="width:100%;">
                                                                                        <?php foreach ($fields as $fk): ?>
                                                                                                <option value="<?php echo esc_attr($fk); ?>" <?php selected($fk, $config['match_field'] ?? ''); ?>><?php echo esc_html($fk); ?></option>
                                                                                        <?php endforeach; ?>
                                                                                    </select>
                                                                                </label>
                                                                            </div>
                                                                            <button type="button" class="button button-small wsm-start-import" data-fid="<?php echo esc_attr($form->id); ?>" style="margin-top:12px; width:100%;">Start Import</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p><button type="submit" class="button button-primary">Save Settings</button>
                                    <span id="wsm-settings-msg" style="margin-left:12px;font-weight:600;"></span></p>
                                </form>
                            </div>

                            <!-- Right: Drag to reorder tracked forms -->
                            <div class="wsm-settings-section wsm-section-tabs">
                                <h3 style="margin-top:0">2. Reorder Tabs <span style="font-size:12px;font-weight:400;color:#666;">(drag to rearrange)</span></h3>
                                <p style="font-size:13px;color:#666;margin-top:-8px;">Only tracked forms appear here.</p>
                                <ul id="wsm-sortable-forms" style="list-style:none;margin:0;padding:0;">
                                    <?php
                                    foreach ($tracked_ids as $fid):
                                        $fname = '';
                                        foreach ($all_forms as $f) {
                                            if ((string) $f->id === (string) $fid) {
                                                $fname = $f->name;
                                                break;
                                            }
                                        }
                                        if (!$fname)
                                            continue;
                                        ?>
                                        <li class="wsm-sort-item" data-id="<?php echo esc_attr($fid); ?>">
                                            <span class="wsm-drag-handle" title="Drag to reorder">⠿</span>
                                            <span class="wsm-sort-label"><?php echo esc_html($fname); ?></span>
                                            <span class="wsm-sort-id" style="color:#999;font-size:12px;"> #<?php echo esc_html($fid); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p id="wsm-order-status" style="font-size:13px;font-weight:600;min-height:20px;margin-top:8px;"></p>
                                <p style="font-size:12px;color:#888;">Order saves automatically as you drag.</p>

                                <hr style="margin:20px 0;">
                                <h3 style="margin-top:0">3. Global Settings</h3>
                                <form id="wsm-global-settings">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><label for="wsm_default_cc">Default Country Code</label></th>
                                            <td>
                                                <input type="text" id="wsm_default_cc" name="wsm_default_cc" value="<?php echo esc_attr($default_cc); ?>" class="small-text" />
                                                <p class="description">Added to legacy numbers that match the Local Number Length below.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="wsm_local_number_length">Local Number Length</label></th>
                                            <td>
                                                <input type="number" id="wsm_local_number_length" name="wsm_local_number_length" value="<?php echo esc_attr($local_len); ?>" class="small-text" min="5" max="20" />
                                                <p class="description">Usually 10 for UK mobile numbers without leading zero.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
                <?php
    }

    public function save_settings()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');

        global $wpdb;
        $form_ids = array_map('intval', (array) ($_POST['form_ids'] ?? []));
        $wpdb->query("DELETE FROM " . WSM_TABLE_FORMS);
        foreach ($form_ids as $fid) {
            if ($fid > 0)
                $wpdb->insert(WSM_TABLE_FORMS, ['form_id' => $fid], ['%d']);
        }

        $order = array_map('intval', (array) ($_POST['form_order'] ?? []));
        update_option('wsm_form_order', $order);

        $duplicate_fields = $_POST['duplicate_fields'] ?? [];
        $sanitized_dups = [];
        foreach ($duplicate_fields as $fid => $fields) {
            $sanitized_dups[intval($fid)] = sanitize_text_field($fields);
        }
        update_option('wsm_duplicate_fields', $sanitized_dups);

        $track_dups = array_map('intval', (array) ($_POST['track_dup_forms'] ?? []));
        update_option('wsm_track_duplicate_forms', $track_dups);

        update_option('wsm_default_cc', sanitize_text_field($_POST['wsm_default_cc'] ?? '44'));
        update_option('wsm_local_number_length', intval($_POST['wsm_local_number_length'] ?? 10));

        wp_send_json_success();
    }

    public function get_csv_headers()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');

        if (empty($_FILES['file']['tmp_name']))
            wp_send_json_error('No file.');

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$handle)
            wp_send_json_error('Cannot open file.');

        // Handle BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF")
            rewind($handle);

        $headers = fgetcsv($handle);
        fclose($handle);

        if (!$headers)
            wp_send_json_error('Empty CSV.');
        wp_send_json_success($headers);
    }

    public function import_legacy()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');

        $form_id = intval($_POST['form_id']);
        $uid_col = intval($_POST['uid_col']);
        $val_col = intval($_POST['val_col']);
        $match_field = sanitize_text_field($_POST['match_field']);

        if (empty($_FILES['file']['tmp_name']))
            wp_send_json_error('No file.');

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$handle)
            wp_send_json_error('Cannot open file.');

        // Encoding & BOM
        $content = file_get_contents($_FILES['file']['tmp_name']);
        if (mb_detect_encoding($content, 'UTF-8', true) === false) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = ltrim($content, "\xEF\xBB\xBF");
        $tmp_handle = fopen('php://temp', 'r+');
        fwrite($tmp_handle, $content);
        rewind($tmp_handle);

        $headers = fgetcsv($tmp_handle);
        $default_cc = get_option('wsm_default_cc', '44');
        $local_len = intval(get_option('wsm_local_number_length', 10));

        global $wpdb;
        $wpdb->query("START TRANSACTION");
        WSM_Data::delete_legacy_data($form_id);

        $count = 0;
        while (($row = fgetcsv($tmp_handle)) !== false) {
            $uid = sanitize_text_field($row[$uid_col] ?? '');
            $raw_val = $row[$val_col] ?? '';
            $normalised = WSM_Data::wsm_normalise_phone($raw_val, $default_cc, $local_len);

            if ($uid && $normalised) {
                $wpdb->insert(WSM_TABLE_LEGACY, [
                    'form_id' => $form_id,
                    'legacy_uid' => $uid,
                    'match_value' => $normalised,
                ], ['%d', '%s', '%s']);
                $count++;
            }
        }
        $wpdb->query("COMMIT");
        fclose($tmp_handle);

        // Save legacy config
        $legacy_config = get_option('wsm_legacy_config', []);
        $legacy_config[$form_id] = [
            'match_field' => $match_field,
            'uid_col' => $uid_col,
            'val_col' => $val_col,
        ];
        update_option('wsm_legacy_config', $legacy_config);

        wp_send_json_success(['count' => $count]);
    }

    public function clear_legacy()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');

        $form_id = intval($_POST['form_id']);
        WSM_Data::delete_legacy_data($form_id);

        $legacy_config = get_option('wsm_legacy_config', []);
        unset($legacy_config[$form_id]);
        update_option('wsm_legacy_config', $legacy_config);

        wp_send_json_success();
    }

    public function save_order()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');

        $order = array_map('intval', (array) ($_POST['order'] ?? []));
        update_option('wsm_form_order', $order);
        wp_send_json_success();
    }
}
