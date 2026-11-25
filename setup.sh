#!/bin/sh
# One-shot: start WordPress, install core + the plugins the live site uses. Safe to re-run.
set -e
cd "$(dirname "$0")"
. ./.env
mkdir -p wordpress mu-plugins
docker compose up -d db wordpress
wp() { docker compose run --rm -T cli php -d memory_limit=2G /usr/local/bin/wp "$@"; }

until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do sleep 2; done
until wp db check >/dev/null 2>&1; do sleep 2; done

wp core is-installed 2>/dev/null || wp core install --url=http://localhost:8080 --title="Infinity Homes" \
  --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email=admin@infinityhomes.test --skip-email

# Same URL shape as the live site: posts /slug.html, pages /slug
wp rewrite structure '/%postname%.html'
# Secure Custom Fields = ACF with the PRO features (repeater, gallery, options pages) the site's field groups use.
wp plugin is-installed advanced-custom-fields && wp plugin uninstall advanced-custom-fields --deactivate
wp plugin install woocommerce secure-custom-fields contact-form-7 polylang theme-translation-for-polylang \
  yith-woocommerce-ajax-navigation variation-price-display wp-security-audit-log wordpress-importer \
  seo-by-rank-math woo-checkout-field-editor-pro --activate
wp option update woocommerce_currency VND
wp option update woocommerce_default_country VN
wp option update woocommerce_price_num_decimals 0
wp option update timezone_string Asia/Ho_Chi_Minh
# Temporary classic theme (widgets + customizer) until Flatsome is installed
wp theme install storefront --activate
wp option update woocommerce_coming_soon no
wp option update pll_dismissed_notices '["wizard","pllwc","review","lingotek"]' --format=json
echo "setup done"
