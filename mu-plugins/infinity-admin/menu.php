<?php
// Admin menu laid out like MatBao WS. Core screens MatBao hides (Appearance, Plugins, Tools, Settings, CF7, SCF)
// are moved under one "System" page at the bottom instead of being locked away.
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    // Statistical
    add_menu_page('Statistical', 'Statistical', 'manage_options', 'mbws-log-views', 'mbws_page_views', 'dashicons-chart-area', 70);
    add_submenu_page('mbws-log-views', 'Access Logs', 'Access Logs', 'manage_options', 'mbws-log-views', 'mbws_page_views');
    add_submenu_page('mbws-log-views', 'Contact List', 'Contact List', 'manage_options', 'contact-form-listing',
        fn() => mbws_render_cf7_entries('View Form Information', 'Select Form name'));
    add_submenu_page('mbws-log-views', 'Comments', 'Comments', 'moderate_comments', 'edit-comments.php');
    $failed = mbws_failed_mail_count(7);
    add_submenu_page('mbws-log-views', 'Nhật ký Email', 'Nhật ký Email' . ($failed ? ' <span class="awaiting-mod">' . $failed . '</span>' : ''), 'manage_options', 'mbws-email-logs',
        fn() => mbws_render_email_logs(null, true));

    add_menu_page('Customer List', 'Customer List', 'manage_woocommerce', 'customer_list', 'mbws_page_customers', 'dashicons-groups', 71);

    add_menu_page('Form Data', 'Form Data', 'manage_options', 'cf7-form-data', fn() => mbws_render_cf7_entries('Form Data', 'All forms'), 'dashicons-feedback', 72);
    add_submenu_page('cf7-form-data', 'Form Data', 'Form Data', 'manage_options', 'cf7-form-data', fn() => mbws_render_cf7_entries('Form Data', 'All forms'));
    add_submenu_page('cf7-form-data', 'Email Logs', 'Email Logs', 'manage_options', 'cf7-email-logs', fn() => mbws_render_email_logs('cf7', false));

    // Website Settings
    add_menu_page('Website Settings', 'Website Settings', 'manage_options', 'mbws-setting-page', 'mbws_page_general', 'dashicons-admin-settings', 80);
    add_submenu_page('mbws-setting-page', 'General', 'General', 'manage_options', 'mbws-setting-page', 'mbws_page_general');
    add_submenu_page('mbws-setting-page', 'Header and Footer', 'Header and Footer', 'customize', 'customize.php');
    add_submenu_page('mbws-setting-page', 'Contact button', 'Contact button', 'manage_options', 'mbws-option-chat', 'mbws_page_chat');
    add_submenu_page('mbws-setting-page', 'Embed code', 'Embed code', 'manage_options', 'mbws-option-scripts', 'mbws_page_scripts');
    add_submenu_page('mbws-setting-page', 'SMTP', 'SMTP', 'manage_options', 'mbws-option-email', 'mbws_page_email');
    add_submenu_page('mbws-setting-page', 'Thông báo', 'Thông báo', 'manage_options', 'mbws-option-notification', 'mbws_page_notification');
    add_submenu_page('mbws-setting-page', 'Widgets', 'Widgets', 'edit_theme_options', 'widgets.php');
    add_submenu_page('mbws-setting-page', 'Email Logs', 'Email Logs' . ($failed ? ' <span class="awaiting-mod">' . $failed . '</span>' : ''), 'manage_options', 'admin.php?page=mbws-email-logs');
    add_submenu_page('mbws-setting-page', 'Socials', 'Socials', 'manage_options', 'admin.php?page=socials');

    add_submenu_page('edit.php', 'Auto Publish Posts', 'Auto Publish Posts', 'publish_posts', 'mbws-word-to-web', 'mbws_page_autopublish');
    add_menu_page('Menus', 'Menus', 'edit_theme_options', 'nav-menus.php', '', 'dashicons-menu', 22);
    add_menu_page('System', 'System', 'manage_options', 'mbws-system', 'mbws_page_system', 'dashicons-admin-generic', 99);
}, 20);

add_action('admin_menu', function () {
    global $menu, $submenu;
    $rename = [
        'edit.php?post_type=page' => 'Layout page',
        'users.php' => 'User',
        'woocommerce' => 'Shop',
    ];
    foreach ($menu as $i => $item) {
        if (isset($rename[$item[2]])) {
            // keep the update bubble (e.g. "Shop 1")
            $menu[$i][0] = preg_replace('/^[^<]+/', $rename[$item[2]], $item[0]);
        }
    }
    $sub_rename = function ($parent, $map) use (&$submenu) {
        foreach ($submenu[$parent] ?? [] as $i => $s) {
            if (isset($map[$s[2]])) {
                $submenu[$parent][$i][0] = preg_replace('/^[^<]+/', $map[$s[2]] . ' ', $s[0]);
            }
        }
    };
    $sub_rename('edit.php?post_type=page', ['post-new.php?post_type=page' => 'Add Page']);
    $sub_rename('users.php', ['user-new.php' => 'Add User']);
    $sub_rename('edit.php', ['post-new.php' => 'Add Post']);

    // Shop = WooCommerce: Home, Orders, Discount, Customers, Checkout Form, Reports, Settings
    remove_menu_page('woocommerce-marketing');
    $wanted = ['wc-admin' => 'Home', 'wc-orders' => 'Orders', 'edit.php?post_type=shop_order' => 'Orders', 'edit.php?post_type=shop_coupon' => 'Discount',
        'wc-admin&path=/customers' => 'Customers', 'checkout_form_designer' => 'Checkout Form', 'wc-reports' => 'Reports', 'wc-settings' => 'Settings'];
    $shop = [];
    foreach ($submenu['woocommerce'] ?? [] as $s) {
        if (isset($wanted[$s[2]])) {
            $s[0] = preg_replace('/^[^<]+/', $wanted[$s[2]] . ' ', $s[0]);
            $shop[$s[2]] = $s;
        }
    }
    $shop['edit.php?post_type=shop_coupon'] ??= ['Discount', 'edit_shop_coupons', 'edit.php?post_type=shop_coupon'];
    $shop['wc-reports'] ??= ['Reports', 'view_woocommerce_reports', 'admin.php?page=wc-reports'];
    $order = array_flip(array_keys($wanted));
    uksort($shop, fn($a, $b) => $order[$a] <=> $order[$b]);
    $submenu['woocommerce'] = array_values($shop);

    // Hidden like on MatBao; reachable from System.
    foreach (['themes.php', 'plugins.php', 'tools.php', 'options-general.php', 'edit-comments.php', 'wpcf7', 'wpcf7-dashboard',
        'edit.php?post_type=acf-field-group', 'profile.php'] as $slug) {
        remove_menu_page($slug);
    }
    remove_submenu_page('users.php', 'profile.php');
    unset($submenu['index.php']); // MatBao's Dashboard has no submenu (Updates lives under System)
}, 999);

add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', fn() => [
    'index.php', 'edit.php', 'edit.php?post_type=featured_item', 'edit.php?post_type=product', 'woocommerce',
    'wc-admin&path=/analytics/overview', 'edit.php?post_type=page', 'edit.php?post_type=blocks', 'upload.php',
    'nav-menus.php', 'users.php', 'mbws-log-views', 'wsal-auditlog', 'edit.php?post_type=room', 'customer_list',
    'wpxtension', 'cf7-form-data', 'rank-math', 'rank-math-registration',
    'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'yith_plugin_panel',
    'mbws-setting-page', 'mlang',
], 999);

function mbws_page_system() {
    $links = [
        'Appearance → Themes' => 'themes.php', 'Plugins' => 'plugins.php', 'Tools' => 'tools.php', 'Import / Export' => 'import.php',
        'Settings → General' => 'options-general.php', 'Settings → Permalinks' => 'options-permalink.php', 'Contact Forms (CF7)' => 'admin.php?page=wpcf7-dashboard',
        'Custom Fields (SCF)' => 'edit.php?post_type=acf-field-group', 'WooCommerce Status' => 'admin.php?page=wc-status', 'Updates' => 'update-core.php',
        'Your Profile' => 'profile.php',
    ];
    echo '<div class="wrap mbws-wrap"><h1>System</h1><div class="mbws-card"><p>Các mục quản trị nâng cao (Mắt Bão ẩn những mục này).</p><div class="mbws-links">';
    foreach ($links as $label => $url) {
        printf('<a class="button" href="%s">%s</a> ', esc_url(admin_url($url)), esc_html($label));
    }
    echo '</div></div></div>';
}
