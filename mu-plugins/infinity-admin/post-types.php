<?php
// Flatsome registers Portfolio (featured_item), UX Blocks (blocks) and UX templates. Until Flatsome is installed,
// register the same types here so the imported content and the "Portfolio" / "Block layout" menus exist.
defined('ABSPATH') || exit;

add_action('init', function () {
    if (post_type_exists('featured_item')) {
        return; // Flatsome is active and owns these.
    }
    register_post_type('featured_item', [
        'label' => 'Portfolio',
        'labels' => ['name' => 'Portfolio', 'singular_name' => 'Project', 'all_items' => 'All Projects', 'add_new_item' => 'Add Project', 'add_new' => 'Add Project'],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions'],
        'rewrite' => ['slug' => 'projects', 'with_front' => false],
        'show_in_rest' => true,
    ]);
    register_taxonomy('featured_item_category', 'featured_item', [
        'label' => 'Project Categories', 'hierarchical' => true, 'show_admin_column' => true,
        'rewrite' => ['slug' => 'project-category', 'with_front' => false], 'show_in_rest' => true,
    ]);
    register_taxonomy('featured_item_tag', 'featured_item', [
        'label' => 'Project Tags', 'rewrite' => ['slug' => 'project-tag', 'with_front' => false], 'show_in_rest' => true,
    ]);

    register_post_type('blocks', [
        'label' => 'Block layout',
        'labels' => ['name' => 'Block layout', 'singular_name' => 'Block', 'all_items' => 'Block layout', 'add_new_item' => 'Add block', 'add_new' => 'Add block'],
        'public' => false, 'show_ui' => true, 'exclude_from_search' => true,
        'menu_icon' => 'dashicons-screenoptions',
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
    ]);
    register_taxonomy('block_categories', 'blocks', ['label' => 'Block categories', 'hierarchical' => true, 'show_admin_column' => true]);

    register_post_type('ux_template', ['label' => 'UX Templates', 'public' => false, 'show_ui' => false, 'supports' => ['title', 'editor']]);
}, 5);
