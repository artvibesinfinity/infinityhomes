<?php
// Re-download any attachment whose file is missing on disk from the live site, then rebuild its thumbnails.
// Run with `wp eval-file`.
require_once ABSPATH . 'wp-admin/includes/image.php';
global $wpdb;
$base = wp_get_upload_dir()['basedir'];
$fixed = $failed = 0;
foreach ($wpdb->get_results("SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file'") as $row) {
    $file = "$base/$row->meta_value";
    if (file_exists($file)) {
        continue;
    }
    $res = wp_remote_get('https://infinityhomes.vn/wp-content/uploads/' . str_replace('%2F', '/', rawurlencode($row->meta_value)), ['timeout' => 60]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        $failed++;
        continue;
    }
    wp_mkdir_p(dirname($file));
    file_put_contents($file, wp_remote_retrieve_body($res));
    wp_update_attachment_metadata($row->post_id, wp_generate_attachment_metadata($row->post_id, $file));
    $fixed++;
}
echo "re-downloaded $fixed missing files, $failed not available on the live site\n";
