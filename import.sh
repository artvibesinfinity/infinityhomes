#!/bin/sh
# Import data/content.xml (Tools → Export from infinityhomes.vn). Run after setup.sh. Safe to re-run.
set -e
cd "$(dirname "$0")"
wp() { docker compose run --rm -T cli php -d memory_limit=2G /usr/local/bin/wp "$@"; }

# Menu items are always re-created by the importer, so clear them first to avoid duplicates.
ids=$(wp post list --post_type=nav_menu_item --post_status=any --format=ids)
[ -n "$ids" ] && wp post delete $ids --force
wp import /data/content.xml --authors=create --require=/data/import-match.php
wp search-replace 'https://infinityhomes.vn' 'http://localhost:8080' --all-tables --skip-columns=guid
wp eval-file /data/fix-translations.php
wp eval-file /data/repair-media.php
# Point WooCommerce at the site's own Shop/Cart/Checkout/My account pages
wp eval 'foreach (["shop"=>"shop","cart"=>"cart","checkout"=>"checkout","myaccount"=>"my-account"] as $k => $slug) { $p = get_page_by_path($slug); if ($p) update_option("woocommerce_{$k}_page_id", $p->ID); }'

# Site logo for the admin sidebar / login screen
logo=$(wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key='_wp_attached_file' AND meta_value LIKE '%infinity-homes-logo%' ORDER BY post_id LIMIT 1" --skip-column-names)
[ -n "$logo" ] && wp eval "\$g = get_option('mbws_general', []); \$g['logo'] = $logo; update_option('mbws_general', \$g); set_theme_mod('custom_logo', $logo);"
wp rewrite flush
echo "import done"
