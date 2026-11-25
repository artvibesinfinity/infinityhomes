<?php
// Dashboard: "Website package info" + "Website report", and the failed-email warning shown on every admin page.
defined('ABSPATH') || exit;

// Plan limits shown in "Website package info" (same numbers as the MatBao "Web - Cao cấp" package).
const MBWS_PACKAGE = 'Web - Cao cấp';
const MBWS_STORAGE_MB = 5000;
const MBWS_PAGES_LIMIT = 100;
const MBWS_EMAILS_LIMIT = 1000;

function mbws_storage_mb() {
    $mb = get_transient('mbws_storage_mb');
    if ($mb === false) {
        global $wpdb;
        $bytes = 0;
        $dir = wp_get_upload_dir()['basedir'];
        if (is_dir($dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
                $bytes += $f->getSize();
            }
        }
        $bytes += (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema=%s", DB_NAME));
        $mb = (int) round($bytes / MB_IN_BYTES);
        set_transient('mbws_storage_mb', $mb, 6 * HOUR_IN_SECONDS);
    }
    return (int) $mb;
}

function mbws_emails_this_month() {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . mbws_table('email_logs') . " WHERE status='sent' AND created_at >= %s", current_time('Y-m-01')));
}

function mbws_usage_card($icon, $label, $used, $limit) {
    $pct = $limit ? min(100, round($used / $limit * 100, 1)) : 0;
    printf('<div class="mbws-usage"><div class="mbws-usage-label"><span class="dashicons %s"></span> %s</div><div class="mbws-usage-num"><strong>%s</strong> <span>/ %s</span></div><div class="mbws-usage-pct">Used %s%%</div><div class="mbws-bar"><i style="width:%s%%"></i></div></div>',
        esc_attr($icon), esc_html($label), number_format_i18n($used), number_format_i18n($limit), number_format_i18n($pct, 1), $pct);
}

function mbws_package_info() {
    if (isset($_GET['mbws_refresh'])) {
        delete_transient('mbws_storage_mb');
    }
    echo '<div class="mbws-card"><h2>Website package info <span class="mbws-pill">✓ ' . esc_html(MBWS_PACKAGE) . '</span> <a class="mbws-refresh" href="' . esc_url(add_query_arg('mbws_refresh', 1)) . '" title="Refresh"><span class="dashicons dashicons-update"></span></a></h2><div class="mbws-grid-3">';
    mbws_usage_card('dashicons-database', 'Data storage', mbws_storage_mb(), MBWS_STORAGE_MB);
    mbws_usage_card('dashicons-layout', 'Layout pages', (int) wp_count_posts('page')->publish, MBWS_PAGES_LIMIT);
    mbws_usage_card('dashicons-email', 'Email / Month', mbws_emails_this_month(), MBWS_EMAILS_LIMIT);
    echo '</div></div>';
}

function mbws_report_card($color, $icon, $value, $label) {
    printf('<div class="mbws-stat"><span class="mbws-stat-ic" style="background:%s"><span class="dashicons %s"></span></span><div><strong>%s</strong><small>%s</small></div></div>',
        esc_attr($color), esc_attr($icon), $value, esc_html($label));
}

function mbws_website_report() {
    $orders = fn($status) => function_exists('wc_orders_count') ? wc_orders_count($status) : 0;
    $revenue = 0;
    if (function_exists('wc_get_orders')) {
        foreach (wc_get_orders(['limit' => -1, 'status' => ['completed', 'processing'], 'date_created' => '>=' . current_time('Y-m-01')]) as $o) {
            $revenue += (float) $o->get_total();
        }
    }
    $customers = count_users()['avail_roles']['customer'] ?? 0;
    echo '<div class="mbws-card"><h2>Website report</h2><div class="mbws-grid-4">';
    mbws_report_card('#f2557a', 'dashicons-money-alt', number_format_i18n($orders('pending')), 'Pending orders');
    mbws_report_card('#f5a623', 'dashicons-archive', number_format_i18n($orders('processing')), 'Processing orders');
    mbws_report_card('#35b88f', 'dashicons-yes-alt', number_format_i18n($orders('completed')), 'Completed orders');
    mbws_report_card('#3ab4f2', 'dashicons-clipboard', number_format_i18n($revenue) . ' VNĐ', 'Revenue of the month');
    mbws_report_card('#7b3ff2', 'dashicons-visibility', number_format_i18n(mbws_views_on(current_time('Y-m-d'))), 'Page views today');
    mbws_report_card('#36cfc9', 'dashicons-groups', number_format_i18n($customers), 'Customers');
    mbws_report_card('#f08a5d', 'dashicons-products', number_format_i18n((int) wp_count_posts('product')->publish), 'Products');
    mbws_report_card('#5b7cfa', 'dashicons-media-document', number_format_i18n((int) wp_count_posts('post')->publish), 'Posts');
    echo '</div></div>';
}

// Replace the stock dashboard with the two panels.
add_action('wp_dashboard_setup', function () {
    global $wp_meta_boxes;
    $wp_meta_boxes['dashboard'] = [];
    remove_action('welcome_panel', 'wp_welcome_panel');
}, 999);
add_filter('get_user_metadata', fn($v, $id, $key) => $key === 'show_welcome_panel' ? [1] : $v, 10, 3);
add_action('welcome_panel', function () {
    mbws_package_info();
    mbws_website_report();
});

// Failed-email warning on every admin screen, hideable for 24h.
add_action('admin_notices', function () {
    if (!current_user_can('manage_options') || (int) get_user_meta(get_current_user_id(), 'mbws_hide_mail_warning', true) > time()) {
        return;
    }
    $n = mbws_failed_mail_count(7);
    if (!$n) {
        return;
    }
    printf('<div class="notice mbws-mail-warning"><p><strong>⚠️ Cảnh báo gửi Email: Có %d email gửi thất bại hoặc bị hệ thống chặn trong 7 ngày qua.</strong> Vui lòng kiểm tra để chắc chắn không bị sót đơn hàng/liên hệ do sai địa chỉ email nhận hoặc lỗi cấu hình.</p><p><a class="button mbws-btn-red" href="%s">Xem danh sách Email bị lỗi</a> <a class="button mbws-btn-blue" href="%s">Ẩn 24h</a></p></div>',
        $n, esc_url(admin_url('admin.php?page=mbws-email-logs&status=failed')), esc_url(wp_nonce_url(admin_url('admin-post.php?action=mbws_hide_mail_warning'), 'mbws_hide')));
});
add_action('admin_post_mbws_hide_mail_warning', function () {
    check_admin_referer('mbws_hide');
    update_user_meta(get_current_user_id(), 'mbws_hide_mail_warning', time() + DAY_IN_SECONDS);
    wp_safe_redirect(wp_get_referer() ?: admin_url());
    exit;
});
