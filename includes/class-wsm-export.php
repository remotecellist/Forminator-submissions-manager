<?php
if (!defined('ABSPATH'))
    exit;

class WSM_Export
{

    public function __construct()
    {
        add_action('admin_init', [$this, 'maybe_export']);
    }

    public function maybe_export()
    {
        if (!isset($_GET['wsm_export']))
            return;
        if (!current_user_can('manage_options'))
            wp_die('Forbidden');
        check_admin_referer('wsm_export');

        global $wpdb;
        $form_id = intval($_GET['form_id'] ?? 0);
        $status_filter = sanitize_text_field($_GET['status_filter'] ?? '');
        $entry_ids_raw = sanitize_text_field($_GET['entry_ids'] ?? '');
        $search_query = sanitize_text_field($_GET['search'] ?? '');

        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        $meta_table = $wpdb->prefix . 'frmt_form_entry_meta';

        if ($entry_ids_raw) {
            $ids = implode(',', array_map('intval', explode(',', $entry_ids_raw)));
            $entries = $wpdb->get_results("SELECT entry_id, date_created FROM $entry_table e WHERE e.entry_id IN ($ids) ORDER BY e.entry_id DESC");
        } else {
            $sql = "SELECT e.entry_id, e.date_created FROM $entry_table e WHERE e.form_id = %d ";
            $params = [$form_id];

            if ($search_query !== '') {
                $sql .= " AND EXISTS (SELECT 1 FROM $meta_table m WHERE m.entry_id = e.entry_id AND m.meta_value LIKE %s) ";
                $params[] = '%' . $wpdb->esc_like($search_query) . '%';
            }
            $sql .= " ORDER BY e.entry_id DESC";
            $entries = $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        if (empty($entries))
            wp_die('No entries to export.');

        $ids_in = implode(',', array_map('intval', array_column($entries, 'entry_id')));
        $metas = $wpdb->get_results("SELECT entry_id, meta_key, meta_value FROM $meta_table WHERE entry_id IN ($ids_in)");
        $wsm_rows = $wpdb->get_results("SELECT entry_id, status, notes, updated_at FROM " . WSM_TABLE_ENTRIES . " WHERE entry_id IN ($ids_in)");

        $meta_map = $wsm_map = [];
        foreach ($metas as $m)
            $meta_map[$m->entry_id][$m->meta_key] = $m->meta_value;
        foreach ($wsm_rows as $w)
            $wsm_map[$w->entry_id] = $w;

        $all_keys = [];
        foreach ($entries as $e) {
            foreach (array_keys($meta_map[$e->entry_id] ?? []) as $k) {
                if (!in_array($k, $all_keys))
                    $all_keys[] = $k;
            }
        }

        if ($status_filter) {
            $entries = array_filter($entries, function ($e) use ($wsm_map, $status_filter) {
                $st = $wsm_map[$e->entry_id]->status ?? 'New';
                return $st === $status_filter;
            });
        }

        $filename = 'wsm-export-form' . $form_id . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        $header = array_merge(
            ['Entry ID', 'Date Submitted', 'Status', 'Notes', 'Last Updated'],
            array_map(fn($k) => ucwords(str_replace(['-', '_'], ' ', $k)), $all_keys)
        );
        fputcsv($out, $header);

        foreach ($entries as $e) {
            $wsm = $wsm_map[$e->entry_id] ?? null;
            $fields = $meta_map[$e->entry_id] ?? [];
            $row = [
                $e->entry_id,
                $e->date_created,
                $wsm->status ?? 'New',
                $wsm->notes ?? '',
                $wsm->updated_at ?? '',
            ];
            foreach ($all_keys as $k)
                $row[] = $fields[$k] ?? '';
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }
}
