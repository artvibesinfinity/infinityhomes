<?php
// "Statistical" + "Form Data" + "Customer List" screens.
defined('ABSPATH') || exit;

function mbws_csv_download($filename, array $header, array $rows) {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads Vietnamese correctly
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    exit;
}

function mbws_pagination($total, $per_page, $paged) {
    $pages = max(1, (int) ceil($total / $per_page));
    if ($pages < 2) {
        return;
    }
    echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links([
        'base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $pages,
        'prev_text' => '‹', 'next_text' => '›',
    ]) . '</div></div>';
}

// ---------------------------------------------------------------- Access Logs
function mbws_page_views() {
    global $wpdb;
    $t = mbws_table('views');
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per = 10;
    $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT day) FROM $t");
    $days = $wpdb->get_results($wpdb->prepare("SELECT day, SUM(visits) visits FROM $t GROUP BY day ORDER BY day DESC LIMIT %d OFFSET %d", $per, ($paged - 1) * $per));
    echo '<div class="wrap mbws-wrap"><h1>Traffic Statistics</h1><div class="mbws-card">';
    echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Visits</th><th></th></tr></thead><tbody>';
    if (!$days) {
        echo '<tr><td colspan="3">No visits recorded yet.</td></tr>';
    }
    foreach ($days as $d) {
        $urls = $wpdb->get_results($wpdb->prepare("SELECT url, visits FROM $t WHERE day=%s ORDER BY visits DESC LIMIT 50", $d->day));
        printf('<tr><td>%s</td><td>%s</td><td><button type="button" class="button-link mbws-toggle">Show more details</button></td></tr>',
            esc_html(wp_date('d/m/Y', strtotime($d->day))), number_format_i18n($d->visits));
        echo '<tr class="mbws-details" hidden><td colspan="3"><table class="widefat"><thead><tr><th>URL</th><th>Visits</th></tr></thead><tbody>';
        foreach ($urls as $u) {
            printf('<tr><td><a href="%s" target="_blank">%s</a></td><td>%d</td></tr>', esc_url(home_url($u->url)), esc_html(urldecode($u->url)), $u->visits);
        }
        echo '</tbody></table></td></tr>';
    }
    echo '</tbody></table>';
    mbws_pagination($total, $per, $paged);
    echo '</div></div>';
}

// ---------------------------------------------------------------- Email logs (Nhật ký Email / Form Data → Email Logs)
function mbws_email_log_query($source, $for_export = false) {
    global $wpdb;
    $t = mbws_table('email_logs');
    $where = ['1=1'];
    $args = [];
    if ($source) {
        $where[] = 'source=%s';
        $args[] = $source;
    }
    $status = sanitize_key($_GET['status'] ?? '');
    if (in_array($status, ['sent', 'failed'], true)) {
        $where[] = 'status=%s';
        $args[] = $status;
    }
    if (preg_match('/^(\d{4})-(\d{2})$/', $_GET['month_filter'] ?? '', $m)) {
        $where[] = 'YEAR(created_at)=%d AND MONTH(created_at)=%d';
        array_push($args, $m[1], $m[2]);
    }
    $s = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
    if ($s !== '') {
        $like = '%' . $wpdb->esc_like($s) . '%';
        $where[] = '(recipient LIKE %s OR subject LIKE %s OR error LIKE %s)';
        array_push($args, $like, $like, $like);
    }
    $sql = "FROM $t WHERE " . implode(' AND ', $where);
    return [$args ? $wpdb->prepare($sql, $args) : $sql, $t];
}

function mbws_render_email_logs($source, $vi) {
    global $wpdb;
    $L = $vi
        ? ['title' => 'Nhật ký Email (Đã gửi & Bị lỗi/chặn)', 'all' => 'Tất cả trạng thái', 'sent' => 'Đã gửi (Sent)', 'failed' => 'Thất bại / Bị chặn (Failed)', 'months' => 'Tất cả các tháng', 'search' => 'Tìm người nhận, tiêu đề, lỗi...', 'go' => 'Tìm kiếm', 'cols' => ['Người nhận (To)', 'Tiêu đề & Chi tiết lỗi', 'Trạng thái', 'Thời gian', 'Thao tác'], 'detail' => 'Xem chi tiết', 'del' => 'Xóa các mục đã chọn', 'export' => 'Tải xuống Excel', 'empty' => 'Chưa có email nào.', 'detail_title' => 'Chi tiết Email']
        : ['title' => 'Email Logs', 'all' => 'All Statuses', 'sent' => 'Sent', 'failed' => 'Failed', 'months' => 'All Months', 'search' => 'Search recipient, subject...', 'go' => 'Search', 'cols' => ['Recipient', 'Subject', 'Status', 'Sent at', 'Actions'], 'detail' => 'Details', 'del' => 'Delete selected', 'export' => null, 'empty' => 'No emails yet.', 'detail_title' => 'Email Details'];

    if (!empty($_POST['log_ids']) && check_admin_referer('mbws_logs_delete') && current_user_can('manage_options')) {
        $ids = array_map('absint', (array) $_POST['log_ids']);
        $wpdb->query("DELETE FROM " . mbws_table('email_logs') . " WHERE id IN (" . implode(',', $ids) . ")");
        echo '<div class="notice notice-success"><p>Deleted ' . count($ids) . '.</p></div>';
    }
    [$from, $t] = mbws_email_log_query($source);
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per = 20;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) $from");
    $rows = $wpdb->get_results("SELECT * $from ORDER BY id DESC LIMIT $per OFFSET " . (($paged - 1) * $per));
    $months = $wpdb->get_col("SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') m FROM $t ORDER BY m DESC");

    echo '<div class="wrap mbws-wrap"><h1>' . esc_html($L['title']) . '</h1>';
    if (($_GET['page'] ?? '') === 'mbws-email-logs') {
        mbws_settings_tabs('mbws-email-logs');
    }
    echo '<div class="mbws-card"><form method="get" class="mbws-filters"><input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '">';
    printf('<select name="status"><option value="">%s</option><option value="sent"%s>%s</option><option value="failed"%s>%s</option></select>',
        esc_html($L['all']), selected($_GET['status'] ?? '', 'sent', false), esc_html($L['sent']), selected($_GET['status'] ?? '', 'failed', false), esc_html($L['failed']));
    echo '<select name="month_filter"><option value="">' . esc_html($L['months']) . '</option>';
    foreach ($months as $m) {
        printf('<option value="%s"%s>%s</option>', esc_attr($m), selected($_GET['month_filter'] ?? '', $m, false), esc_html(substr($m, 5) . '/' . substr($m, 0, 4)));
    }
    printf('</select> <input type="search" name="s" value="%s" placeholder="%s"> <button class="button">%s</button>',
        esc_attr(wp_unslash($_GET['s'] ?? '')), esc_attr($L['search']), esc_html($L['go']));
    if ($L['export']) {
        printf(' <a class="button" href="%s">%s</a>', esc_url(wp_nonce_url(add_query_arg(['action' => 'mbws_export_emails', 'source' => $source] + array_intersect_key($_GET, array_flip(['status', 'month_filter', 's'])), admin_url('admin-post.php')), 'mbws_export')), esc_html($L['export']));
    }
    echo '</form><form method="post">';
    wp_nonce_field('mbws_logs_delete');
    echo '<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" class="mbws-check-all"></td>';
    foreach ($L['cols'] as $c) {
        echo '<th>' . esc_html($c) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="6">' . esc_html($L['empty']) . '</td></tr>';
    }
    foreach ($rows as $r) {
        $ok = $r->status === 'sent';
        printf('<tr><th class="check-column"><input type="checkbox" name="log_ids[]" value="%d"></th><td>%s</td><td><strong>%s</strong>%s</td><td><span class="mbws-badge %s">%s</span></td><td>%s</td><td><button type="button" class="button-link mbws-toggle">%s</button></td></tr>',
            $r->id, esc_html($r->recipient), esc_html($r->subject), $r->error ? '<br><span class="mbws-error">' . esc_html($r->error) . '</span>' : '',
            $ok ? 'ok' : 'fail', esc_html($ok ? $L['sent'] : $L['failed']), esc_html(wp_date('d/m/Y H:i', strtotime($r->created_at))), esc_html($L['detail']));
        printf('<tr class="mbws-details" hidden><td></td><td colspan="5"><h4>%s</h4><p><strong>Headers:</strong><br><code>%s</code></p><div class="mbws-mail-body">%s</div></td></tr>',
            esc_html($L['detail_title']), nl2br(esc_html($r->headers)), wp_kses_post(wpautop($r->message)));
    }
    echo '</tbody></table><p><button class="button button-link-delete" onclick="return confirm(\'Delete?\')">' . esc_html($L['del']) . '</button></p></form>';
    mbws_pagination($total, $per, $paged);
    echo '</div></div>';
}

add_action('admin_post_mbws_export_emails', function () {
    check_admin_referer('mbws_export');
    current_user_can('manage_options') || wp_die('Not allowed.');
    global $wpdb;
    $source = sanitize_key($_GET['source'] ?? '') ?: null;
    [$from] = mbws_email_log_query($source);
    $rows = $wpdb->get_results("SELECT recipient, subject, status, error, created_at $from ORDER BY id DESC", ARRAY_N);
    mbws_csv_download('email-logs-' . wp_date('Ymd') . '.csv', ['Người nhận', 'Tiêu đề', 'Trạng thái', 'Lỗi', 'Thời gian'], $rows);
});

// ---------------------------------------------------------------- Contact List / Form Data (CF7 entries)
function mbws_render_cf7_entries($title, $all_label) {
    global $wpdb;
    $forms = get_posts(['post_type' => 'wpcf7_contact_form', 'numberposts' => -1]);
    $form_id = absint($_GET['form_id'] ?? 0);
    echo '<div class="wrap mbws-wrap"><h1>' . esc_html($title) . '</h1><div class="mbws-card"><form method="get"><input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '">';
    echo '<select name="form_id" onchange="this.form.submit()"><option value="">' . esc_html($all_label) . '</option>';
    foreach ($forms as $f) {
        printf('<option value="%d"%s>%s</option>', $f->ID, selected($form_id, $f->ID, false), esc_html($f->post_title));
    }
    echo '</select></form>';
    $t = mbws_table('cf7_entries');
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per = 20;
    $where = $form_id ? $wpdb->prepare('WHERE form_id=%d', $form_id) : '';
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t $where");
    $rows = $wpdb->get_results("SELECT * FROM $t $where ORDER BY id DESC LIMIT $per OFFSET " . (($paged - 1) * $per));
    $cols = [];
    foreach ($rows as $r) {
        $r->data = json_decode($r->data, true) ?: [];
        $cols = array_unique(array_merge($cols, array_keys($r->data)));
    }
    echo '<table class="widefat striped"><thead><tr><th>Date</th>' . ($form_id ? '' : '<th>Form</th>');
    foreach ($cols as $c) {
        echo '<th>' . esc_html($c) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . (count($cols) + 2) . '">No submissions yet.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr><td>' . esc_html(wp_date('d/m/Y H:i', strtotime($r->created_at))) . '</td>' . ($form_id ? '' : '<td>' . esc_html(get_the_title($r->form_id)) . '</td>');
        foreach ($cols as $c) {
            $v = $r->data[$c] ?? '';
            echo '<td>' . esc_html(is_array($v) ? implode(', ', $v) : $v) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    mbws_pagination($total, $per, $paged);
    echo '</div></div>';
}

// ---------------------------------------------------------------- Customer List (from WooCommerce orders + registered customers)
function mbws_customers($search = '') {
    $list = [];
    if (function_exists('wc_get_orders')) {
        foreach (wc_get_orders(['limit' => -1, 'type' => 'shop_order']) as $o) {
            $email = strtolower($o->get_billing_email());
            if (!$email) {
                continue;
            }
            $c = $list[$email] ?? ['name' => trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name()), 'email' => $email,
                'phone' => $o->get_billing_phone(), 'address' => implode(', ', array_filter([$o->get_billing_address_1(), $o->get_billing_city()])),
                'orders' => 0, 'spent' => 0, 'last' => ''];
            $c['orders']++;
            if ($o->has_status(['completed', 'processing'])) {
                $c['spent'] += (float) $o->get_total();
            }
            $date = $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i') : '';
            $c['last'] = max($c['last'], $date);
            $list[$email] = $c;
        }
    }
    foreach (get_users(['role' => 'customer']) as $u) {
        $email = strtolower($u->user_email);
        $list[$email] ??= ['name' => $u->display_name, 'email' => $email, 'phone' => get_user_meta($u->ID, 'billing_phone', true),
            'address' => get_user_meta($u->ID, 'billing_address_1', true), 'orders' => 0, 'spent' => 0, 'last' => ''];
    }
    if ($search !== '') {
        $list = array_filter($list, fn($c) => stripos(implode(' ', $c), $search) !== false);
    }
    uasort($list, fn($a, $b) => strcmp($b['last'], $a['last']));
    return array_values($list);
}

function mbws_page_customers() {
    $search = sanitize_text_field(wp_unslash($_GET['search_text'] ?? ''));
    $rows = mbws_customers($search);
    echo '<div class="wrap mbws-wrap"><h1>Customer List</h1><div class="mbws-card"><form method="get" class="mbws-filters"><input type="hidden" name="page" value="customer_list">';
    printf('<input type="search" name="search_text" value="%s" placeholder="Search..."> <button class="button">Search</button> <a class="button" href="%s">Export Excel</a></form>',
        esc_attr($search), esc_url(wp_nonce_url(add_query_arg(['action' => 'mbws_export_customers', 'search_text' => $search], admin_url('admin-post.php')), 'mbws_export')));
    echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Orders</th><th>Total spent</th><th>Last order</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="7">No customers yet.</td></tr>';
    }
    foreach ($rows as $c) {
        printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>', esc_html($c['name']), esc_html($c['email']),
            esc_html($c['phone']), esc_html($c['address']), $c['orders'], function_exists('wc_price') ? wc_price($c['spent']) : esc_html($c['spent']), esc_html($c['last']));
    }
    echo '</tbody></table></div></div>';
}

add_action('admin_post_mbws_export_customers', function () {
    check_admin_referer('mbws_export');
    current_user_can('manage_woocommerce') || wp_die('Not allowed.');
    $rows = array_map(fn($c) => [$c['name'], $c['email'], $c['phone'], $c['address'], $c['orders'], $c['spent'], $c['last']],
        mbws_customers(sanitize_text_field(wp_unslash($_GET['search_text'] ?? ''))));
    mbws_csv_download('customers-' . wp_date('Ymd') . '.csv', ['Name', 'Email', 'Phone', 'Address', 'Orders', 'Total spent', 'Last order'], $rows);
});
