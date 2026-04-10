<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSM_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'wp_ajax_wsm_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_wsm_save_order', [ $this, 'save_order' ] );
    }

    public function add_menu() {
        $total_new = WSM_Data::get_total_new_entries();
        $menu_title = 'Submissions';
        if ( $total_new > 0 ) {
            $menu_title .= ' <span class="update-plugins count-' . esc_attr( $total_new ) . '"><span class="plugin-count">' . esc_html( number_format_i18n( $total_new ) ) . '</span></span>';
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
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        $all_forms   = WSM_Data::get_forminator_forms();
        $tracked_ids = WSM_Data::get_tracked_form_ids();
        $dup_fields_map = get_option( 'wsm_duplicate_fields', [] );
        $dup_form_ids = get_option( 'wsm_track_duplicate_forms', [] );
        ?>
        <div class="wrap wsm-wrap">
            <h1>⚙️ Web Submissions Manager &mdash; Settings</h1>
            <p>Select which Forminator forms to track. Drag tracked forms to reorder their tab appearance.</p>

            <?php if ( empty( $all_forms ) ) :
                global $wpdb;
                $all_tables = $wpdb->get_col( "SHOW TABLES" );
                $fi_tables  = array_filter( $all_tables, fn($t) => stripos($t,'frmt') !== false || stripos($t,'forminator') !== false );
                $fi_pts     = $wpdb->get_col( "SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_type LIKE '%forminator%' OR post_type LIKE '%frm%'" );
            ?>
                <div class="notice notice-warning">
                    <p><strong>No Forminator forms found.</strong></p>
                    <p><strong>DB tables with "frmt" or "forminator":</strong> <?php echo $fi_tables ? esc_html( implode( ', ', $fi_tables ) ) : '<em>none</em>'; ?></p>
                    <p><strong>Post types in wp_posts matching "forminator" or "frm":</strong> <?php echo $fi_pts ? esc_html( implode( ', ', $fi_pts ) ) : '<em>none</em>'; ?></p>
                    <p>Share the above with your developer to fix the query.</p>
                </div>
            <?php else : ?>
            <div style="display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap;">

                <!-- Left: Select forms to track -->
                <div style="flex:1;min-width:280px;">
                    <h3 style="margin-top:0">1. Select Forms to Track</h3>
                    <form id="wsm-settings-form">
                        <table class="widefat striped">
                            <thead>
                                <tr><th>Form ID</th><th>Form Name</th><th>Track?</th><th>Track Duplicates?</th><th>Duplicate Track Fields (comma-separated)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $all_forms as $form ) : 
                                    $dup_string = isset($dup_fields_map[$form->id]) ? $dup_fields_map[$form->id] : '';
                                    $dup_arr = array_filter(array_map('trim', explode(',', $dup_string)));
                                    $fields = WSM_Data::get_form_fields_for_settings( $form->id );
                                    $is_dup_tracked = in_array( (string) $form->id, array_map('strval', $dup_form_ids) );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $form->id ); ?></td>
                                    <td><?php echo esc_html( $form->name ); ?></td>
                                    <td>
                                        <input type="checkbox" class="wsm-form-checkbox" name="form_ids[]"
                                            value="<?php echo esc_attr( $form->id ); ?>"
                                            <?php checked( in_array( (string) $form->id, array_map('strval', $tracked_ids) ) ); ?> />
                                    </td>
                                    <td>
                                        <input type="checkbox" class="wsm-track-dups-checkbox" name="track_dup_forms[]"
                                            value="<?php echo esc_attr( $form->id ); ?>"
                                            <?php checked( $is_dup_tracked ); ?> />
                                    </td>
                                    <td>
                                        <div class="wsm-duplicate-fields-container" style="<?php echo $is_dup_tracked ? '' : 'display:none;'; ?>">
                                            <?php if ( empty($fields) ) : ?>
                                                <em style="font-size: 12px; color: #666;">No entries found to determine fields. Fallback text input:</em>
                                                <input type="text" class="wsm-duplicate-fields-input wsm-input" name="duplicate_fields[<?php echo esc_attr($form->id); ?>]" value="<?php echo esc_attr($dup_string); ?>" placeholder="e.g. phone-1, text-1" style="width: 100%; margin-top: 4px;" />
                                            <?php else : ?>
                                                <div class="wsm-duplicate-fields-options" data-fid="<?php echo esc_attr($form->id); ?>" style="max-height: 120px; overflow-y: auto; padding: 6px; border: 1px solid #ddd; background: #fafafa; border-radius: 4px;">
                                                <?php foreach ( $fields as $fkey ) :
                                                    $flabel = ucwords( str_replace( ['-', '_'], ' ', $fkey ) );
                                                ?>
                                                    <label style="display:block; margin-bottom: 4px; font-size: 13px;">
                                                        <input type="checkbox" class="wsm-dup-check" value="<?php echo esc_attr($fkey); ?>" <?php checked(in_array($fkey, $dup_arr)); ?>> <?php echo esc_html($flabel); ?>
                                                        <span style="color:#999; font-size:11px;">(<?php echo esc_html($fkey); ?>)</span>
                                                    </label>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><button type="submit" class="button button-primary">Save Settings</button>
                        <span id="wsm-settings-msg" style="margin-left:12px;font-weight:600;"></span></p>
                    </form>
                </div>

                <!-- Right: Drag to reorder tracked forms -->
                <div style="flex:1;min-width:260px;">
                    <h3 style="margin-top:0">2. Reorder Tabs <span style="font-size:12px;font-weight:400;color:#666;">(drag to rearrange)</span></h3>
                    <p style="font-size:13px;color:#666;margin-top:-8px;">Only tracked forms appear here.</p>
                    <ul id="wsm-sortable-forms" style="list-style:none;margin:0;padding:0;">
                        <?php
                        foreach ( $tracked_ids as $fid ) :
                            $fname = '';
                            foreach ( $all_forms as $f ) {
                                if ( (string) $f->id === (string) $fid ) { $fname = $f->name; break; }
                            }
                            if ( ! $fname ) continue;
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
                </div>

            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_settings() {
        check_ajax_referer( 'wsm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

        global $wpdb;
        $form_ids = array_map( 'intval', (array) ( $_POST['form_ids'] ?? [] ) );
        $wpdb->query( "DELETE FROM " . WSM_TABLE_FORMS );
        foreach ( $form_ids as $fid ) {
            if ( $fid > 0 ) $wpdb->insert( WSM_TABLE_FORMS, [ 'form_id' => $fid ], [ '%d' ] );
        }

        $order = array_map( 'intval', (array) ( $_POST['form_order'] ?? [] ) );
        update_option( 'wsm_form_order', $order );

        $duplicate_fields = $_POST['duplicate_fields'] ?? [];
        $sanitized_dups = [];
        foreach ($duplicate_fields as $fid => $fields) {
            $sanitized_dups[intval($fid)] = sanitize_text_field($fields);
        }
        update_option('wsm_duplicate_fields', $sanitized_dups);

        $track_dups = array_map( 'intval', (array) ( $_POST['track_dup_forms'] ?? [] ) );
        update_option('wsm_track_duplicate_forms', $track_dups);

        wp_send_json_success();
    }

    public function save_order() {
        check_ajax_referer( 'wsm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

        $order = array_map( 'intval', (array) ( $_POST['order'] ?? [] ) );
        update_option( 'wsm_form_order', $order );
        wp_send_json_success();
    }
}
