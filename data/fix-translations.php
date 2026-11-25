<?php
// Polylang keeps EN<->VI links as old post IDs inside each post_translations term; rebuild them from the
// posts actually attached to each term after import. Run with `wp eval-file`.
$fixed = 0;
foreach (get_terms(['taxonomy' => 'post_translations', 'hide_empty' => false]) as $term) {
    $map = [];
    foreach (get_objects_in_term($term->term_id, 'post_translations') as $id) {
        $lang = pll_get_post_language((int) $id);
        if ($lang) {
            $map[$lang] = (int) $id;
        }
    }
    if (count($map) > 1) {
        pll_save_post_translations($map);
        $fixed++;
    }
}
echo "linked $fixed translation groups\n";
