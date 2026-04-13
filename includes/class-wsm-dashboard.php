<?php
if (!defined('ABSPATH'))
    exit;

class WSM_Dashboard
{
    public function __construct()
    {
        add_action('wp_ajax_wsm_save', [$this, 'ajax_save']);
        add_action('wp_ajax_wsm_bulk_save', [$this, 'ajax_bulk_save']);
        add_action('wp_ajax_wsm_bulk_status', [$this, 'ajax_bulk_status']);
        add_action('wp_ajax_wsm_search_entries', [$this, 'ajax_search_entries']);
    }

    public function ajax_save()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Unauthorized');

        $entry_id = intval($_POST['entry_id'] ?? 0);
        $fid = intval($_POST['form_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$entry_id)
            wp_send_json_error('Missing Entry ID');

        WSM_Data::update_wsm_data($entry_id, $fid, $status, $notes);

        $counts = [
            'form_new_count' => WSM_Data::count_new_entries($fid),
            'total_new_count' => WSM_Data::count_total_new_entries()
        ];

        wp_send_json_success($counts);
    }

    public function ajax_bulk_save()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Unauthorized');

        $rows = $_POST['rows'] ?? [];
        $form_id = intval($_POST['form_id']);
        $saved = 0;

        foreach ($rows as $r) {
            $eid = intval($r['entry_id'] ?? 0);
            if (!$eid)
                continue;
            $status = sanitize_text_field($r['status'] ?? '');
            $notes = sanitize_textarea_field($r['notes'] ?? '');
            WSM_Data::update_wsm_data($eid, $form_id, $status, $notes);
            $saved++;
        }

        wp_send_json_success([
            'saved' => $saved,
            'form_new_count' => WSM_Data::count_new_entries($form_id),
            'total_new_count' => WSM_Data::count_total_new_entries()
        ]);
    }

    public function ajax_bulk_status()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Unauthorized');

        $fid = intval($_POST['form_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $ids = isset($_POST['entry_ids']) ? array_map('intval', $_POST['entry_ids']) : [];

        if (!$fid || !$status || empty($ids)) {
            wp_send_json_error('Missing data');
        }

        foreach ($ids as $entry_id) {
            WSM_Data::update_wsm_data($entry_id, $fid, $status);
        }

        $counts = [
            'form_new_count' => WSM_Data::count_new_entries($fid),
            'total_new_count' => WSM_Data::count_total_new_entries()
        ];

        wp_send_json_success($counts);
    }

    public static function render_entry_rows($entries, $active_form_id, $match_field = '', $legacy_matches = [], $duplicates = [])
    {
        if (empty($entries)) {
            echo '<tr><td colspan="7" style="text-align:center;padding:40px;">No entries found.</td></tr>';
            return;
        }

        $config_maps = WSM_Data::get_form_config_maps($active_form_id);
        $field_labels = $config_maps['field_labels'] ?? [];
        $value_labels = $config_maps['value_labels'] ?? [];

        foreach ($entries as $entry):
            $wsm = WSM_Data::get_wsm_data($entry->entry_id);
            $cur_status = $wsm->status ?? 'New';
            $cur_notes = $wsm->notes ?? '';
            $updated = $wsm ? date_i18n('d M Y', strtotime($wsm->updated_at)) : '';
            $is_new = ($cur_status === 'New');
            ?>
            <tr class="wsm-row <?php echo $is_new ? 'wsm-row-new' : ''; ?>" data-entry="<?php echo esc_attr($entry->entry_id); ?>"
                data-form="<?php echo esc_attr($active_form_id); ?>">
                <td data-label="ID">
                    <input type="checkbox" class="wsm-row-check" value="<?php echo esc_attr($entry->entry_id); ?>">
                    &nbsp;<strong>#<?php echo esc_html($entry->entry_id); ?></strong>
                    <?php if ($is_new): ?>
                        <span class="wsm-row-new-dot" title="New submission">●</span>
                    <?php endif; ?>
                </td>
                <td style="display:none"></td>
                <td data-label="Date Created" style="font-size:12px;">
                    <?php echo esc_html(date_i18n('d M Y H:i', strtotime($entry->date_created))); ?>
                </td>
                <td class="wsm-fields" data-label="Fields">
                    <?php
                    if ($match_field) {
                        $raw_val = $entry->fields[$match_field] ?? '';
                        $norm = WSM_Data::wsm_normalise_phone($raw_val, get_option('wsm_default_cc', '44'), intval(get_option('wsm_local_number_length', 10)));
                        if (isset($legacy_matches[$norm])) {
                            $uids = $legacy_matches[$norm];
                            echo '<div class="wsm-badge-legacy" title="Matched in legacy data">Legacy: #' . esc_html(implode(', #', $uids)) . '</div><br>';
                        }
                    }

                    foreach ($entry->fields as $key => $val):
                        $label = $field_labels[$key] ?? ucwords(str_replace(['-', '_'], ' ', $key));
                        $display_val = $value_labels[$key][$val] ?? $val;

                        // Handle serialized values and arrays
                        $unserialized = maybe_unserialize($display_val);
                        if (is_array($unserialized)) {
                            if (isset($unserialized['formatting_result'])) {
                                $display_val = $unserialized['formatting_result'];
                            } elseif (isset($unserialized['street_address'])) {
                                $addr = array_filter([
                                    $unserialized['street_address'] ?? '',
                                    $unserialized['city'] ?? '',
                                    $unserialized['state'] ?? '',
                                    $unserialized['zip'] ?? '',
                                    $unserialized['country'] ?? ''
                                ]);
                                $display_val = implode(', ', $addr);
                            } else {
                                $display_val = implode(', ', array_filter($unserialized));
                            }
                        }

                        $is_dup = isset($duplicates[$key][$val]);
                        $is_first = $is_dup && $duplicates[$key][$val]['first_entry_id'] == $entry->entry_id;
                        ?>
                        <div class="wsm-field <?php echo $is_dup ? 'wsm-duplicate-field' : ''; ?>">
                            <span class="wsm-label"><strong><?php echo esc_html($label); ?>:</strong></span>
                            <?php echo esc_html($display_val); ?>
                            <?php if ($is_first): ?>
                                <span class="wsm-dup-badge wsm-dup-original" title="This is the original entry">🟢 1st Submission</span>
                            <?php elseif ($is_dup): ?>
                                <span class="wsm-dup-badge" title="Found <?php echo esc_attr($duplicates[$key][$val]['cnt']); ?> times">⚠️
                                    Duplicate</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td data-label="Status">
                    <select class="wsm-status-select">
                        <?php foreach (WSM_Data::get_statuses() as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($cur_status, $s); ?>><?php echo esc_html($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($updated): ?>
                        <div style="font-size:11px;color:#888;margin-top:4px;">Updated: <?php echo esc_html($updated); ?></div>
                    <?php endif; ?>
                </td>
                <td data-label="Notes">
                    <textarea class="wsm-notes" rows="3"
                        placeholder="Add notes here..."><?php echo esc_textarea($cur_notes); ?></textarea>
                </td>
                <td data-label="Action">
                    <button class="button button-primary wsm-save-btn">Save</button>
                    <div class="wsm-msg"></div>
                </td>
            </tr>
        <?php endforeach;
    }

    public static function render_pagination($total_pgs, $current_pg, $active_form_id, $status_filter = '', $search_query = '')
    {
        if ($total_pgs <= 1)
            return;
        ?>
        <div class="wsm-pagination">
            <?php for ($i = 1; $i <= $total_pgs; $i++):
                $pg_url = admin_url("admin.php?page=wsm-dashboard&form_id=$active_form_id&paged=$i" . ($status_filter ? "&status_filter=" . urlencode($status_filter) : '') . ($search_query ? "&search=" . urlencode($search_query) : '')); ?>
                <a href="<?php echo esc_url($pg_url); ?>"
                    class="button <?php echo $i == $current_pg ? 'button-primary' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <span class="wsm-page-info">Page <?php echo $current_pg; ?> of <?php echo $total_pgs; ?></span>
        </div>
        <?php
    }

    public function ajax_search_entries()
    {
        check_ajax_referer('wsm_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_die('Unauthorized');

        $active_form_id = intval($_POST['form_id'] ?? 0);
        $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
        $search_query = sanitize_text_field($_POST['search'] ?? '');
        $paged = max(1, intval($_POST['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $entries = WSM_Data::get_forminator_entries($active_form_id, $per_page, $offset, $status_filter, $search_query);
        $total = WSM_Data::count_entries($active_form_id, $status_filter, $search_query);
        $total_pgs = (int) ceil($total / $per_page);

        $legacy_config = get_option('wsm_legacy_config', []);
        $config = $legacy_config[$active_form_id] ?? [];
        $match_field = $config['match_field'] ?? '';
        $legacy_matches = [];
        if ($match_field && !empty($entries)) {
            $vals_to_check = [];
            foreach ($entries as $e) {
                if (!empty($e->fields[$match_field])) {
                    $norm = WSM_Data::wsm_normalise_phone($e->fields[$match_field], get_option('wsm_default_cc', '44'), intval(get_option('wsm_local_number_length', 10)));
                    if ($norm)
                        $vals_to_check[] = $norm;
                }
            }
            if (!empty($vals_to_check)) {
                $legacy_matches = WSM_Data::get_legacy_matches($active_form_id, array_unique($vals_to_check));
            }
        }

        $duplicates = [];
        $all_dup_fields = get_option('wsm_duplicate_fields', []);
        $all_dup_form_ids = get_option('wsm_track_duplicate_forms', []);
        if (in_array($active_form_id, $all_dup_form_ids) && !empty($entries)) {
            $form_dup_fields = isset($all_dup_fields[$active_form_id]) ? array_filter(array_map('trim', explode(',', $all_dup_fields[$active_form_id]))) : [];
            if (!empty($form_dup_fields)) {
                $values_to_check = [];
                foreach ($entries as $e) {
                    foreach ($form_dup_fields as $fk) {
                        if (!empty($e->fields[$fk]))
                            $values_to_check[$fk][] = $e->fields[$fk];
                    }
                }
                global $wpdb;
                $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';
                $entry_table = $wpdb->prefix . 'frmt_form_entry';
                foreach ($values_to_check as $fk => $vals) {
                    $vals = array_unique($vals);
                    if (empty($vals))
                        continue;
                    $placeholders = implode(',', array_fill(0, count($vals), '%s'));
                    $query = $wpdb->prepare("SELECT m.meta_value, COUNT(m.meta_id) as cnt, MIN(e.entry_id) as first_entry_id FROM $meta_table m JOIN $entry_table e ON m.entry_id = e.entry_id WHERE e.form_id = %d AND m.meta_key = %s AND m.meta_value IN ($placeholders) GROUP BY m.meta_value HAVING cnt > 1", array_merge([$active_form_id, $fk], $vals));
                    $results = $wpdb->get_results($query);
                    foreach ($results as $r) {
                        $duplicates[$fk][$r->meta_value] = ['cnt' => $r->cnt, 'first_entry_id' => $r->first_entry_id];
                    }
                }
            }
        }

        ob_start();
        self::render_entry_rows($entries, $active_form_id, $match_field, $legacy_matches, $duplicates);
        $rows_html = ob_get_clean();

        ob_start();
        self::render_pagination($total_pgs, $paged, $active_form_id, $status_filter, $search_query);
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'rows' => $rows_html,
            'pagination' => $pagination_html,
            'total' => $total
        ]);
    }
}

function wsm_page_dashboard()
{
    $tracked_ids = WSM_Data::get_tracked_form_ids();

    if (empty($tracked_ids)) {
        echo '<div class="wrap wsm-wrap"><h1>📋 Forminator Submissions Manager</h1>';
        echo '<div class="notice notice-info"><p>No forms tracked yet. Go to <a href="' . admin_url('admin.php?page=wsm-settings') . '">Settings</a> to add forms.</p></div></div>';
        return;
    }

    $active_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : $tracked_ids[0];
    if (!in_array($active_form_id, $tracked_ids))
        $active_form_id = $tracked_ids[0];

    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $per_page = 20;
    $current_pg = max(1, intval($_GET['paged'] ?? 1));
    $offset = ($current_pg - 1) * $per_page;

    $entries = WSM_Data::get_forminator_entries($active_form_id, $per_page, $offset, $status_filter, $search_query);
    $total = WSM_Data::count_entries($active_form_id, $status_filter, $search_query);

    // ─── Legacy Match Bulk Lookup ───
    $legacy_config = get_option('wsm_legacy_config', []);
    $config = $legacy_config[$active_form_id] ?? [];
    $match_field = $config['match_field'] ?? '';
    $legacy_matches = [];

    if ($match_field && !empty($entries)) {
        $vals_to_check = [];
        $default_cc = get_option('wsm_default_cc', '44');
        $local_len = intval(get_option('wsm_local_number_length', 10));
        foreach ($entries as $e) {
            $raw = $e->fields[$match_field] ?? '';
            if ($raw) {
                $normalised = WSM_Data::wsm_normalise_phone($raw, $default_cc, $local_len);
                if ($normalised)
                    $vals_to_check[] = $normalised;
            }
        }
        if (!empty($vals_to_check)) {
            $legacy_matches = WSM_Data::get_legacy_matches($active_form_id, array_unique($vals_to_check));
        }
    }
    $total_pgs = (int) ceil($total / $per_page);

    $all_dup_fields = get_option('wsm_duplicate_fields', []);
    $all_dup_form_ids = get_option('wsm_track_duplicate_forms', []);

    $form_dup_fields = [];
    if (in_array($active_form_id, $all_dup_form_ids)) {
        $form_dup_fields = isset($all_dup_fields[$active_form_id]) ? array_map('trim', explode(',', $all_dup_fields[$active_form_id])) : [];
        $form_dup_fields = array_filter($form_dup_fields);
    }

    $duplicates = [];
    if (!empty($form_dup_fields) && !empty($entries)) {
        $values_to_check = [];
        foreach ($entries as $e) {
            foreach ($form_dup_fields as $fk) {
                if (!empty($e->fields[$fk])) {
                    $values_to_check[$fk][] = $e->fields[$fk];
                }
            }
        }

        global $wpdb;
        $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';
        $entry_table = $wpdb->prefix . 'frmt_form_entry';

        foreach ($values_to_check as $fk => $vals) {
            $vals = array_unique($vals);
            if (empty($vals))
                continue;

            $placeholders = implode(',', array_fill(0, count($vals), '%s'));
            $query = $wpdb->prepare("
                SELECT m.meta_value, COUNT(m.meta_id) as cnt, MIN(e.entry_id) as first_entry_id
                FROM $meta_table m
                JOIN $entry_table e ON m.entry_id = e.entry_id
                WHERE e.form_id = %d AND m.meta_key = %s AND m.meta_value IN ($placeholders)
                GROUP BY m.meta_value
                HAVING cnt > 1
            ", array_merge([$active_form_id, $fk], $vals));

            $results = $wpdb->get_results($query);
            foreach ($results as $r) {
                $duplicates[$fk][$r->meta_value] = [
                    'cnt' => $r->cnt,
                    'first_entry_id' => $r->first_entry_id
                ];
            }
        }
    }

    $all_forms = WSM_Data::get_forminator_forms();
    $form_names = [];
    foreach ($all_forms as $f)
        $form_names[$f->id] = $f->name;

    $new_counts = [];
    foreach ($tracked_ids as $fid) {
        $new_counts[$fid] = WSM_Data::count_new_entries($fid);
    }

    ?>
    <div class="wrap wsm-wrap">
        <h1>📋 Forminator Submissions Manager</h1>

        <div class="wsm-tabs-container">
            <button type="button" class="wsm-tab-scroll left"
                onclick="document.querySelector('.wsm-tabs').scrollBy({left: -150, behavior: 'smooth'})">❮</button>
            <div class="wsm-tabs">
                <?php foreach ($tracked_ids as $fid):
                    $url = admin_url("admin.php?page=wsm-dashboard&form_id=$fid");
                    $new_cnt = $new_counts[$fid] ?? 0;
                    $is_active = ($fid == $active_form_id);
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="wsm-tab <?php echo $is_active ? 'wsm-tab-active' : ''; ?>"
                        data-tab-fid="<?php echo esc_attr($fid); ?>">
                        <?php echo esc_html($form_names[$fid] ?? "Form #$fid"); ?>
                        <?php if ($new_cnt > 0): ?>
                            <span class="wsm-new-badge" title="<?php echo esc_attr($new_cnt); ?> new submission(s)">
                                <?php echo esc_html($new_cnt > 99 ? '99+' : $new_cnt); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="wsm-tab-scroll right"
                onclick="document.querySelector('.wsm-tabs').scrollBy({left: 150, behavior: 'smooth'})">❯</button>
        </div>

        <div class="wsm-filters">
            <strong>Filter:</strong>
            <?php $search_param_url = $search_query ? '&search=' . urlencode($search_query) : ''; ?>
            <a href="<?php echo esc_url(admin_url("admin.php?page=wsm-dashboard&form_id=$active_form_id$search_param_url")); ?>"
                class="wsm-badge <?php echo $status_filter === '' ? 'wsm-badge-active' : ''; ?>">All</a>
            <?php foreach (WSM_Data::get_statuses() as $s):
                $url = admin_url("admin.php?page=wsm-dashboard&form_id=$active_form_id&status_filter=" . urlencode($s) . $search_param_url);
                $col = WSM_Data::get_status_color($s); ?>
                <a href="<?php echo esc_url($url); ?>"
                    class="wsm-badge <?php echo $status_filter === $s ? 'wsm-badge-active' : ''; ?>"
                    style="--badge-color:<?php echo $col; ?>">
                    <?php echo esc_html($s); ?>
                </a>
            <?php endforeach; ?>
            <span class="wsm-total">
                <?php echo $total; ?> total entries
            </span>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="wsm-search-form"
                style="display:inline-block; margin-left:15px;">
                <input type="hidden" name="page" value="wsm-dashboard">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($active_form_id); ?>">
                <?php if ($status_filter): ?>
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <input type="text" name="search" id="wsm-search-input" placeholder="Search fields..."
                    value="<?php echo esc_attr($search_query); ?>"
                    style="padding:2px 6px; font-size:13px; vertical-align:middle;">
                <button type="submit" class="button button-small" style="vertical-align:middle;">Search</button>
                <?php if ($search_query): ?>
                    <a href="<?php echo esc_url(admin_url("admin.php?page=wsm-dashboard&form_id=$active_form_id" . ($status_filter ? '&status_filter=' . urlencode($status_filter) : ''))); ?>"
                        style="font-size:12px; margin-left:5px; text-decoration:none; color:#a00;">&times; Clear</a>
                <?php endif; ?>
                <span id="wsm-search-spinner" class="spinner" style="float:none; margin:0 0 0 5px;"></span>
            </form>
        </div>

        <?php
        $export_nonce = wp_create_nonce('wsm_export');
        $export_base = admin_url('admin.php?wsm_export=1&form_id=' . $active_form_id . '&_wpnonce=' . $export_nonce);
        $export_all_url = $export_base . ($status_filter ? '&status_filter=' . urlencode($status_filter) : '') . $search_param_url;
        ?>

        <?php if (empty($entries) && !$search_query): ?>
            <p>No submissions found.</p>
        <?php else: ?>

            <div class="wsm-toolbar" id="wsm-toolbar">
                <label class="wsm-toolbar-check">
                    <input type="checkbox" id="wsm-check-all"> <span>Select All</span>
                </label>
                <span class="wsm-toolbar-sep"></span>

                <span id="wsm-bulk-controls" style="display:none">
                    <label>Bulk set status:</label>
                    <select id="wsm-bulk-status-select">
                        <option value="">— choose —</option>
                        <?php foreach (WSM_Data::get_statuses() as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button" id="wsm-bulk-status-btn">Apply to Selected</button>
                    <button class="button" id="wsm-bulk-export-btn">⬇ Export Selected</button>
                    <span id="wsm-bulk-msg" style="font-weight:600;margin-left:8px;"></span>
                </span>

                <div class="wsm-toolbar-right">
                    <button class="button button-primary" id="wsm-save-all-btn">💾 Save All</button>
                    <span id="wsm-saveall-msg" style="font-weight:600;margin-left:8px;"></span>
                    <a href="<?php echo esc_url($export_all_url); ?>" class="button" id="wsm-export-all-btn">⬇ Export
                        <?php echo $status_filter ? esc_html($status_filter) : 'All'; ?> (CSV)</a>
                </div>
            </div>

            <table class="widefat wsm-table" id="wsm-table">
                <thead>
                    <tr>
                        <th style="width:120px"><input type="checkbox" id="wsm-head-check" style="margin-right:6px"> ID</th>
                        <th style="display:none"></th>
                        <th style="width:130px">Date</th>
                        <th>Submission Fields</th>
                        <th style="width:155px">Status</th>
                        <th>Notes</th>
                        <th style="width:90px">Save</th>
                    </tr>
                </thead>
                <tbody id="wsm-entries-body">
                    <?php WSM_Dashboard::render_entry_rows($entries, $active_form_id, $match_field, $legacy_matches, $duplicates); ?>
                </tbody>
            </table>

            <div id="wsm-pagination-container">
                <?php WSM_Dashboard::render_pagination($total_pgs, $current_pg, $active_form_id, $status_filter, $search_query); ?>
            </div>

        <?php endif; ?>
    </div>
    <?php
}
