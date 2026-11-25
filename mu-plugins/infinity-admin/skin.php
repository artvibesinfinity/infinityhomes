<?php
// MatBao WS look: white sidebar with logo, blue active pill, white top bar with "Live" badge and Technical Support.
defined('ABSPATH') || exit;

function mbws_logo_url() {
    $id = (int) (get_option('mbws_general')['logo'] ?? 0) ?: (int) get_theme_mod('custom_logo');
    return $id ? wp_get_attachment_image_url($id, 'medium') : '';
}

add_action('admin_enqueue_scripts', function () {
    $base = content_url('mu-plugins/infinity-admin/assets/');
    wp_enqueue_style('mbws-admin', $base . 'admin.css', [], '1');
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_media();
    wp_enqueue_script('mbws-admin', $base . 'admin.js', ['jquery', 'wp-color-picker'], '1', true);
    $logo = mbws_logo_url();
    wp_add_inline_style('mbws-admin', $logo
        ? '#adminmenuwrap::before{background-image:url(' . esc_url($logo) . ')}'
        : '#adminmenuwrap::before{content:"' . esc_attr(get_bloginfo('name')) . '";background:none}');
});

add_action('admin_bar_menu', function (WP_Admin_Bar $bar) {
    if (!is_admin()) {
        return;
    }
    foreach (['wp-logo', 'comments', 'new-content', 'updates', 'customize', 'search', 'woocommerce-site-visibility-badge'] as $id) {
        $bar->remove_node($id);
    }
    $site = $bar->get_node('site-name');
    if ($site) {
        $site->title = esc_html(get_bloginfo('name')) . ' <span class="mbws-live">Live</span>';
        $bar->add_node((array) $site);
    }
    $g = get_option('mbws_general', []);
    $bar->add_node(['id' => 'mbws-support', 'parent' => 'top-secondary', 'title' => '<span class="dashicons dashicons-editor-help"></span> Technical Support']);
    $bar->add_node(['id' => 'mbws-support-docs', 'parent' => 'mbws-support', 'title' => 'Hướng dẫn sử dụng WordPress', 'href' => 'https://wordpress.org/documentation/', 'meta' => ['target' => '_blank']]);
    $bar->add_node(['id' => 'mbws-support-woo', 'parent' => 'mbws-support', 'title' => 'Hướng dẫn WooCommerce', 'href' => 'https://woocommerce.com/documentation/', 'meta' => ['target' => '_blank']]);
    if (!empty($g['store_email'])) {
        $bar->add_node(['id' => 'mbws-support-mail', 'parent' => 'mbws-support', 'title' => 'Email: ' . esc_html($g['store_email']), 'href' => 'mailto:' . $g['store_email']]);
    }
    if (!empty($g['store_phone'])) {
        $bar->add_node(['id' => 'mbws-support-phone', 'parent' => 'mbws-support', 'title' => 'Hotline: ' . esc_html($g['store_phone']), 'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $g['store_phone'])]);
    }
}, 999);

add_filter('admin_bar_menu', function (WP_Admin_Bar $bar) {
    $acct = $bar->get_node('my-account');
    if ($acct) {
        $acct->title = preg_replace('/Howdy, /', 'Howdy,', $acct->title);
        $bar->add_node((array) $acct);
    }
}, 9999);

add_filter('admin_footer_text', fn() => esc_html(get_bloginfo('name')) . ' — Website Admin');
add_filter('update_footer', '__return_empty_string', 99);

// Login screen with the site logo.
add_action('login_enqueue_scripts', function () {
    $logo = mbws_logo_url();
    echo '<style>body.login{background:#f3f5f9}.login form{border-radius:12px;border:0;box-shadow:0 4px 20px rgba(0,0,0,.06)}.wp-core-ui .button-primary{background:#4a7cf6;border-color:#4a7cf6;border-radius:8px}'
        . ($logo ? '#login h1 a{background-image:url(' . esc_url($logo) . ');background-size:contain;width:240px;height:70px}' : '') . '</style>';
});
add_filter('login_headerurl', fn() => home_url('/'));
