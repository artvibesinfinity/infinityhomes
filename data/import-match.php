<?php
// Loaded with `wp import --require=…` (runs before WordPress loads, so register through WP-CLI).
// The importer keeps each item's original ID, so "already imported" = same ID + same post type.
// Its default check (same title + date) wrongly skips EN/VI translations, and Polylang copies share GUIDs.
// Attachments that are file-duplicates of an existing one (same uploads path) are mapped to it, not re-downloaded.
WP_CLI::add_wp_hook('wp_import_existing_post', function ($exists, $post) {
    $p = get_post((int) $post['post_id']);
    if ($p && $p->post_type === $post['post_type']) {
        return $p->ID;
    }
    if ($post['post_type'] === 'attachment') {
        global $wpdb;
        $url = $post['attachment_url'] ?? $post['guid'];
        $path = ($i = strpos($url, '/wp-content/uploads/')) !== false ? substr($url, $i + 20) : '';
        return $path ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1", $path)) : 0;
    }
    return 0;
}, 10, 2);
