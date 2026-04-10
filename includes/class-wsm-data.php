<?php
if (!defined('ABSPATH'))
    exit;

class WSM_Data
{

    public static function get_statuses()
    {
        return ['New', 'In Progress', 'Done', 'Follow-up', 'Spam', 'Rejected'];
    }

    public static function get_status_color($status)
    {
        $map = [
            'New' => '#2271b1',
            'In Progress' => '#d08000',
            'Done' => '#00a32a',
            'Follow-up' => '#9b59b6',
            'Spam' => '#888888',
            'Rejected' => '#d63638',
        ];
        return $map[$status] ?? '#555';
    }

    public static function get_status_colors()
    {
        $colors = [];
        foreach (self::get_statuses() as $s) {
            $colors[$s] = self::get_status_color($s);
        }
        return $colors;
    }

    public static function get_tracked_form_ids()
    {
        global $wpdb;
        $order = get_option('wsm_form_order', []);
        $ids = $wpdb->get_col("SELECT form_id FROM " . WSM_TABLE_FORMS);

        if (!empty($order)) {
            $ordered = array_filter($order, fn($id) => in_array($id, $ids));
            $rest = array_values(array_diff($ids, $ordered));
            return array_merge(array_values($ordered), $rest);
        }

        return $ids;
    }

    public static function get_forminator_forms()
    {
        global $wpdb;
        $post_types_to_try = ['forminator_forms', 'frm-form', 'forminator-forms'];

        foreach ($post_types_to_try as $pt) {
            $forms = get_posts([
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => ['publish', 'draft', 'any'],
            ]);
            if (!empty($forms)) {
                return array_map(function ($p) {
                    return (object) ['id' => $p->ID, 'name' => $p->post_title ?: "Form #{$p->ID}"];
                }, $forms);
            }
        }

        $tables_to_try = [
            $wpdb->prefix . 'frmt_form',
            $wpdb->prefix . 'forminator_form',
        ];
        foreach ($tables_to_try as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                $rows = $wpdb->get_results("SELECT id, name FROM $table");
                if (!empty($rows))
                    return $rows;
            }
        }

        $results = $wpdb->get_results(
            "SELECT ID as id, post_title as name, post_type
             FROM {$wpdb->posts}
             WHERE post_type LIKE '%forminator%'
             AND post_status NOT IN ('trash','auto-draft')
             ORDER BY ID DESC"
        );
        return $results ?: [];
    }

    public static function get_forminator_entries($form_id, $limit = 20, $offset = 0, $status_filter = '', $search_query = '')
    {
        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';

        if (!$wpdb->get_var("SHOW TABLES LIKE '$entry_table'"))
            return [];

        $sql = "SELECT e.entry_id, e.date_created FROM $entry_table e ";
        $params = [$form_id];
        $where = " WHERE e.form_id = %d ";

        if ($status_filter === 'New') {
            $sql .= " LEFT JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id ";
            $where .= " AND (w.status IS NULL OR w.status = 'New') ";
        } elseif ($status_filter) {
            $sql .= " INNER JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id ";
            $where .= " AND w.status = %s ";
            $params[] = $status_filter;
        }

        if ($search_query !== '') {
            $where .= " AND EXISTS (SELECT 1 FROM $meta_table m WHERE m.entry_id = e.entry_id AND m.meta_value LIKE %s) ";
            $params[] = '%' . $wpdb->esc_like($search_query) . '%';
        }

        $sql .= $where . " ORDER BY e.entry_id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $entries = $wpdb->get_results($wpdb->prepare($sql, $params));
        if (empty($entries))
            return [];

        $entry_ids = implode(',', array_map('intval', array_column($entries, 'entry_id')));
        $metas = $wpdb->get_results("SELECT entry_id, meta_key, meta_value FROM $meta_table WHERE entry_id IN ($entry_ids)");

        $meta_map = [];
        foreach ($metas as $m) {
            $meta_map[$m->entry_id][$m->meta_key] = $m->meta_value;
        }

        foreach ($entries as &$e) {
            $e->fields = $meta_map[$e->entry_id] ?? [];
        }

        return $entries;
    }

    public static function count_entries($form_id, $status_filter = '', $search_query = '')
    {
        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$entry_table'"))
            return 0;

        $sql = "SELECT COUNT(*) FROM $entry_table e ";
        $params = [$form_id];
        $where = " WHERE e.form_id = %d ";

        if ($status_filter === 'New') {
            $sql .= " LEFT JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id ";
            $where .= " AND (w.status IS NULL OR w.status = 'New') ";
        } elseif ($status_filter) {
            $sql .= " INNER JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id ";
            $where .= " AND w.status = %s ";
            $params[] = $status_filter;
        }

        if ($search_query !== '') {
            $where .= " AND EXISTS (SELECT 1 FROM $meta_table m WHERE m.entry_id = e.entry_id AND m.meta_value LIKE %s) ";
            $params[] = '%' . $wpdb->esc_like($search_query) . '%';
        }

        $sql .= $where;
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_wsm_data($entry_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WSM_TABLE_ENTRIES . " WHERE entry_id = %d",
            $entry_id
        ));
    }

    public static function count_new_entries($form_id)
    {
        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$entry_table'"))
            return 0;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$entry_table} e
             LEFT JOIN " . WSM_TABLE_ENTRIES . " w ON e.entry_id = w.entry_id
             WHERE e.form_id = %d
             AND (w.status IS NULL OR w.status = 'New')",
            $form_id
        ));
    }

    public static function get_total_new_entries()
    {
        $tracked_ids = self::get_tracked_form_ids();
        if (empty($tracked_ids))
            return 0;

        $total = 0;
        foreach ($tracked_ids as $fid) {
            $total += self::count_new_entries($fid);
        }
        return $total;
    }

    public static function get_form_fields_for_settings($form_id)
    {
        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';

        if (!$wpdb->get_var("SHOW TABLES LIKE '$entry_table'"))
            return [];

        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT m.meta_key 
             FROM $meta_table m 
             INNER JOIN $entry_table e ON m.entry_id = e.entry_id 
             WHERE e.form_id = %d 
             ORDER BY m.meta_key ASC",
            $form_id
        ));
    }

    public static function wsm_normalise_phone($number, $default_cc = '', $local_len = 0)
    {
        // 1. Strip all non-digits
        $clean = preg_replace('/\D/', '', $number);
        if (empty($clean))
            return '';

        // 2. Handle international prefix 00 (strip it)
        if (strpos($clean, '00') === 0) {
            $clean = substr($clean, 2);
        }

        // 3. Fix missing leading zero (e.g. 7700900123 -> 447700900123)
        // If it matches configured length and doesn't start with 0, prepend CC
        if ($default_cc && $local_len > 0 && strlen($clean) == $local_len && strpos($clean, '0') !== 0) {
            $clean = $default_cc . $clean;
        }

        // 4. Handle leading 0 (replace with CC)
        if (strpos($clean, '0') === 0) {
            $clean = $default_cc . substr($clean, 1);
        }

        // 5. Fix double-prefix (e.g. 44077... -> 4477...)
        if ($default_cc && strpos($clean, $default_cc . '0') === 0) {
            $clean = $default_cc . substr($clean, strlen($default_cc) + 1);
        }

        // 6. Final numeric guard
        return ctype_digit($clean) ? $clean : '';
    }

    public static function get_legacy_matches($form_id, $values)
    {
        if (empty($values))
            return [];
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($values), '%s'));
        $query = $wpdb->prepare(
            "SELECT match_value, legacy_uid FROM " . WSM_TABLE_LEGACY . " 
             WHERE form_id = %d AND match_value IN ($placeholders)",
            array_merge([$form_id], $values)
        );
        $results = $wpdb->get_results($query);

        $map = [];
        foreach ($results as $row) {
            $map[$row->match_value][] = $row->legacy_uid;
        }
        return $map;
    }

    public static function delete_legacy_data($form_id)
    {
        global $wpdb;
        return $wpdb->delete(WSM_TABLE_LEGACY, ['form_id' => $form_id], ['%d']);
    }
}
