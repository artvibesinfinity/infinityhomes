<?php
// Data behind "Statistical": page-view counts, email log (sent/failed), Contact Form 7 entries.
defined('ABSPATH') || exit;

const MBWS_DB_VERSION = '1';

function mbws_table($name) {
    global $wpdb;
    return $wpdb->prefix . 'mbws_' . $name;
}

add_action('init', function () {
    if (get_option('mbws_db_version') === MBWS_DB_VERSION) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE " . mbws_table('views') . " (
        day date NOT NULL,
        url varchar(191) NOT NULL,
        visits int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (day,url)
    ) $c;");
    dbDelta("CREATE TABLE " . mbws_table('email_logs') . " (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        recipient text NOT NULL,
        subject text NOT NULL,
        message longtext NOT NULL,
        headers text NOT NULL,
        status varchar(10) NOT NULL,
        error text NOT NULL,
        source varchar(10) NOT NULL DEFAULT 'wp',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY created_at (created_at)
    ) $c;");
    dbDelta("CREATE TABLE " . mbws_table('cf7_entries') . " (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        form_id bigint unsigned NOT NULL,
        data longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY form_id (form_id)
    ) $c;");
    update_option('mbws_db_version', MBWS_DB_VERSION);
});

// --- Page views: one counter per day+URL, no IPs stored. Admins and bots are not counted.
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || is_preview() || is_404() || is_favicon() || is_robots() || is_feed() || current_user_can('edit_posts')
        || preg_match('/bot|crawl|spider|slurp|preview|lighthouse/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        return;
    }
    global $wpdb;
    $url = substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), 0, 191);
    $wpdb->query($wpdb->prepare(
        "INSERT INTO " . mbws_table('views') . " (day,url,visits) VALUES (%s,%s,1) ON DUPLICATE KEY UPDATE visits=visits+1",
        current_time('Y-m-d'), $url
    ));
});

function mbws_views_on($day) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT SUM(visits) FROM " . mbws_table('views') . " WHERE day=%s", $day));
}

// --- Email log. Mail sent while a CF7 form is being processed is tagged source=cf7.
$GLOBALS['mbws_mail_source'] = 'wp';
add_action('wpcf7_before_send_mail', function () { $GLOBALS['mbws_mail_source'] = 'cf7'; }, 1);
add_action('wpcf7_mail_sent', function () { $GLOBALS['mbws_mail_source'] = 'wp'; });
add_action('wpcf7_mail_failed', function () { $GLOBALS['mbws_mail_source'] = 'wp'; });

function mbws_log_mail(array $mail, $status, $error = '') {
    global $wpdb;
    $to = is_array($mail['to'] ?? '') ? implode(', ', $mail['to']) : (string) ($mail['to'] ?? '');
    $headers = is_array($mail['headers'] ?? '') ? implode("\n", $mail['headers']) : (string) ($mail['headers'] ?? '');
    $wpdb->insert(mbws_table('email_logs'), [
        'recipient' => $to,
        'subject' => (string) ($mail['subject'] ?? ''),
        'message' => (string) ($mail['message'] ?? ''),
        'headers' => $headers,
        'status' => $status,
        'error' => $error,
        'source' => $GLOBALS['mbws_mail_source'],
        'created_at' => current_time('mysql'),
    ]);
}
add_action('wp_mail_succeeded', fn($mail) => mbws_log_mail($mail, 'sent'));
add_action('wp_mail_failed', fn(WP_Error $e) => mbws_log_mail($e->get_error_data() ?: [], 'failed', $e->get_error_message()));

function mbws_failed_mail_count($days = 7) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . mbws_table('email_logs') . " WHERE status='failed' AND created_at >= %s",
        wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
    ));
}

// --- Contact Form 7 entries.
add_action('wpcf7_before_send_mail', function ($form) {
    $sub = class_exists('WPCF7_Submission') ? WPCF7_Submission::get_instance() : null;
    if (!$sub) {
        return;
    }
    $data = array_filter($sub->get_posted_data(), fn($k) => $k[0] !== '_', ARRAY_FILTER_USE_KEY);
    global $wpdb;
    $wpdb->insert(mbws_table('cf7_entries'), [
        'form_id' => $form->id(),
        'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
        'created_at' => current_time('mysql'),
    ]);
}, 5);
