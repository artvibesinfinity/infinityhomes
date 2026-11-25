<?php
// "Website Settings": General, Contact button, Embed code, SMTP, Thông báo — and what each setting does on the site.
// Option names match the MatBao WS ones (mbws_*).
defined('ABSPATH') || exit;

function mbws_settings_tabs($current) {
    $tabs = [
        'mbws-setting-page' => 'General',
        'customize.php' => 'Header and Footer',
        'mbws-option-chat' => 'Contact button',
        'mbws-option-scripts' => 'Embed code',
        'mbws-option-email' => 'SMTP',
        'mbws-option-notification' => 'Thông báo',
        'widgets.php' => 'Widgets',
        'mbws-email-logs' => 'Email Logs',
        'socials' => 'Socials',
    ];
    echo '<nav class="nav-tab-wrapper mbws-tabs">';
    foreach ($tabs as $slug => $label) {
        $url = str_ends_with($slug, '.php') ? admin_url($slug) : admin_url('admin.php?page=' . $slug);
        printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url($url), $slug === $current ? ' nav-tab-active' : '', esc_html($label));
    }
    echo '</nav>';
}

/** Verifies nonce + capability for a settings form; returns true when a save should happen. */
function mbws_saving($action) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['_mbws_nonce'])) {
        return false;
    }
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_mbws_nonce'], $action)) {
        wp_die('Not allowed.');
    }
    return true;
}

function mbws_saved_notice() {
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
}

function mbws_field_row($label, $html, $required = false) {
    printf('<tr><th scope="row">%s%s</th><td>%s</td></tr>', esc_html($label), $required ? ' <span class="required">*</span>' : '', $html);
}

function mbws_image_picker($name, $id) {
    $src = $id ? wp_get_attachment_image_url($id, 'thumbnail') : '';
    return sprintf(
        '<div class="mbws-image"><img src="%s" style="%s"><input type="hidden" name="%s" value="%d">
        <button type="button" class="button mbws-image-choose">Choose image</button> <button type="button" class="button mbws-image-remove">Remove image</button></div>',
        esc_url($src), $src ? '' : 'display:none', esc_attr($name), (int) $id
    );
}

// ---------------------------------------------------------------- General
function mbws_page_general() {
    $g = get_option('mbws_general', []);
    if (mbws_saving('mbws_general')) {
        $p = wp_unslash($_POST);
        update_option('blogname', sanitize_text_field($p['blogname'] ?? ''));
        foreach (['store_address', 'store_phone', 'category', 'language', 'content_style'] as $k) {
            $g[$k] = sanitize_text_field($p[$k] ?? '');
        }
        $g['store_email'] = sanitize_email($p['store_email'] ?? '');
        $g['distinctive'] = sanitize_textarea_field($p['distinctive'] ?? '');
        $g['service_product'] = sanitize_textarea_field($p['service_product'] ?? '');
        $g['logo'] = absint($p['logo'] ?? 0);
        $g['favicon'] = absint($p['favicon'] ?? 0);
        update_option('mbws_general', $g);
        update_option('woocommerce_store_address', $g['store_address']);
        update_option('site_icon', $g['favicon']);
        set_theme_mod('custom_logo', $g['logo']);
        update_option('mbws_color_primary', sanitize_hex_color($p['mbws_color_primary'] ?? '') ?: '');
        update_option('mbws_color_secondary', sanitize_hex_color($p['mbws_color_secondary'] ?? '') ?: '');
        mbws_saved_notice();
    }
    $v = fn($k) => esc_attr($g[$k] ?? '');
    $select = function ($name, $placeholder, $options) use ($g) {
        $html = '<select name="' . esc_attr($name) . '" required><option value="">' . esc_html($placeholder) . '</option>';
        foreach ($options as $o) {
            $html .= sprintf('<option%s>%s</option>', selected($g[$name] ?? '', $o, false), esc_html($o));
        }
        return $html . '</select>';
    };
    echo '<div class="wrap mbws-wrap"><h1>Theme Settings</h1>';
    mbws_settings_tabs('mbws-setting-page');
    mbws_package_info();
    echo '<form method="post">';
    wp_nonce_field('mbws_general', '_mbws_nonce');
    echo '<div class="mbws-card"><h2>Contact information</h2><table class="form-table">';
    mbws_field_row('Website name', '<input class="regular-text" name="blogname" required value="' . esc_attr(get_option('blogname')) . '">', true);
    mbws_field_row('Address', '<input class="regular-text" name="store_address" required value="' . $v('store_address') . '">', true);
    mbws_field_row('Email', '<input class="regular-text" type="email" name="store_email" required value="' . $v('store_email') . '">', true);
    mbws_field_row('Phone number', '<input class="regular-text" name="store_phone" required value="' . $v('store_phone') . '">', true);
    echo '</table></div><div class="mbws-card"><h2>Business information</h2><table class="form-table">';
    mbws_field_row('Website type', $select('category', '-- Select category --', ['Business, consulting', 'E-commerce, retail', 'Education, health, lifestyle', 'Technology, digital services']), true);
    mbws_field_row('Distinctive points', '<textarea class="large-text" rows="3" name="distinctive" required>' . esc_textarea($g['distinctive'] ?? '') . '</textarea>', true);
    mbws_field_row('Services/products', '<textarea class="large-text" rows="3" name="service_product" required>' . esc_textarea($g['service_product'] ?? '') . '</textarea>', true);
    mbws_field_row('Language', '<input class="regular-text" name="language" required value="' . $v('language') . '">', true);
    mbws_field_row('Tone of voice', $select('content_style', '-- Select tone of voice --', [
        'Kể chuyện thương hiệu (Storytelling)', 'Nội dung chuẩn SEO (SEO Content)', 'Tài liệu kỹ thuật (Technical Document)',
        'Trang bán hàng / Landing Page (Marketing Copy)', 'Nội dung học thuật (Academic Content)',
    ]), true);
    echo '</table></div><div class="mbws-card"><h2>Theme customization</h2><table class="form-table">';
    mbws_field_row('Logo', mbws_image_picker('logo', $g['logo'] ?? 0));
    mbws_field_row('Favicon', mbws_image_picker('favicon', $g['favicon'] ?? get_option('site_icon')));
    mbws_field_row('Primary color', '<input class="mbws-color" name="mbws_color_primary" value="' . esc_attr(get_option('mbws_color_primary')) . '">');
    mbws_field_row('Secondary color', '<input class="mbws-color" name="mbws_color_secondary" value="' . esc_attr(get_option('mbws_color_secondary')) . '">');
    echo '</table></div>';
    submit_button('Save Changes');
    echo '</form></div>';
}

// ---------------------------------------------------------------- Contact button
const MBWS_CHAT_TYPES = ['phone' => 'Phone', 'zalo' => 'Zalo', 'messenger' => 'Messenger', 'email' => 'Email', 'link' => 'Custom link'];
const MBWS_CHAT_EFFECTS = ['none' => 'None', 'shake' => 'Shake', 'pulse' => 'Pulse'];

function mbws_chat_settings() {
    return wp_parse_args(get_option('mbws_chat_settings', []), [
        'active' => 'no', 'icon_position' => 'right', 'icon_pLeft' => 20, 'icon_pBottom' => 20,
        'bg_color' => '#ffffff', 'text_color' => '#333333', 'mobile_layout' => 'no', 'min_screen' => 768,
        'icon_width' => 48, 'icon_height' => 48, 'items' => [],
    ]);
}

function mbws_page_chat() {
    $s = mbws_chat_settings();
    if (mbws_saving('mbws_chat')) {
        $p = wp_unslash($_POST['mbws_chat_settings'] ?? []);
        $s = [
            'active' => ($p['active'] ?? '') === 'yes' ? 'yes' : 'no',
            'icon_position' => ($p['icon_position'] ?? '') === 'left' ? 'left' : 'right',
            'mobile_layout' => ($p['mobile_layout'] ?? '') === 'yes' ? 'yes' : 'no',
            'bg_color' => sanitize_hex_color($p['bg_color'] ?? '') ?: '#ffffff',
            'text_color' => sanitize_hex_color($p['text_color'] ?? '') ?: '#333333',
            'items' => [],
        ];
        foreach (['icon_pLeft', 'icon_pBottom', 'min_screen', 'icon_width', 'icon_height'] as $k) {
            $s[$k] = absint($p[$k] ?? 0);
        }
        foreach ((array) ($p['items'] ?? []) as $it) {
            if (empty($it['link'])) {
                continue;
            }
            $s['items'][] = [
                'order' => (int) ($it['order'] ?? 0),
                'type' => array_key_exists($it['type'] ?? '', MBWS_CHAT_TYPES) ? $it['type'] : 'link',
                'title' => sanitize_text_field($it['title'] ?? ''),
                'link' => sanitize_text_field($it['link']),
                'new_tab' => !empty($it['new_tab']),
                'color' => sanitize_hex_color($it['color'] ?? '') ?: '#1a73e8',
                'effect' => array_key_exists($it['effect'] ?? '', MBWS_CHAT_EFFECTS) ? $it['effect'] : 'none',
                'hide_desktop' => !empty($it['hide_desktop']),
                'hide_mobile' => !empty($it['hide_mobile']),
            ];
        }
        usort($s['items'], fn($a, $b) => $a['order'] <=> $b['order']);
        update_option('mbws_chat_settings', $s);
        mbws_saved_notice();
    }
    $n = fn($k) => 'mbws_chat_settings[' . $k . ']';
    $radio = fn($k, $opts) => implode(' ', array_map(fn($val, $lab) => sprintf(
        '<label><input type="radio" name="%s" value="%s"%s> %s</label>', $n($k), esc_attr($val), checked($s[$k], $val, false), esc_html($lab)
    ), array_keys($opts), $opts));
    $num = fn($k, $unit = 'px') => sprintf('<input type="number" class="small-text" name="%s" value="%d"> %s', $n($k), (int) $s[$k], $unit);

    echo '<div class="wrap mbws-wrap"><h1>Theme Settings</h1>';
    mbws_settings_tabs('mbws-option-chat');
    echo '<form method="post"><div class="mbws-card"><h2>Contact button settings</h2><table class="form-table">';
    wp_nonce_field('mbws_chat', '_mbws_nonce');
    mbws_field_row('Enable', $radio('active', ['yes' => 'Yes', 'no' => 'No']));
    mbws_field_row('Position', $radio('icon_position', ['left' => 'Left', 'right' => 'Right']));
    mbws_field_row('Left/Right margin spacing', $num('icon_pLeft'));
    mbws_field_row('Bottom margin spacing', $num('icon_pBottom'));
    mbws_field_row('Background color', '<input class="mbws-color" name="' . $n('bg_color') . '" value="' . esc_attr($s['bg_color']) . '">');
    mbws_field_row('Text color', '<input class="mbws-color" name="' . $n('text_color') . '" value="' . esc_attr($s['text_color']) . '">');
    mbws_field_row('Show horizontal layout on mobile', $radio('mobile_layout', ['yes' => 'Yes', 'no' => 'No']));
    mbws_field_row('Screen size', $num('min_screen') . '<p class="description">Mobile layout applies below this width.</p>');
    mbws_field_row('Icon size', $num('icon_width') . ' × ' . $num('icon_height'));
    echo '</table>';

    $row = function ($i, $it) use ($n) {
        $it = wp_parse_args($it, ['order' => is_int($i) ? $i + 1 : 0, 'type' => 'phone', 'title' => '', 'link' => '', 'new_tab' => false, 'color' => '#1a73e8', 'effect' => 'none', 'hide_desktop' => false, 'hide_mobile' => false]);
        $f = fn($k) => $n('items') . '[' . $i . '][' . $k . ']';
        $opts = fn($list, $cur) => implode('', array_map(fn($v, $l) => sprintf('<option value="%s"%s>%s</option>', esc_attr($v), selected($cur, $v, false), esc_html($l)), array_keys($list), $list));
        return '<tr>'
            . sprintf('<td><input type="number" class="small-text" name="%s" value="%d"></td>', $f('order'), $it['order'])
            . sprintf('<td><select name="%s">%s</select></td>', $f('type'), $opts(MBWS_CHAT_TYPES, $it['type']))
            . sprintf('<td><input name="%s" value="%s"></td>', $f('title'), esc_attr($it['title']))
            . sprintf('<td><input name="%s" value="%s" placeholder="0909… / https://…"></td>', $f('link'), esc_attr($it['link']))
            . sprintf('<td><input type="checkbox" name="%s" value="1"%s></td>', $f('new_tab'), checked($it['new_tab'], true, false))
            . sprintf('<td><input class="mbws-color" name="%s" value="%s"></td>', $f('color'), esc_attr($it['color']))
            . sprintf('<td><select name="%s">%s</select></td>', $f('effect'), $opts(MBWS_CHAT_EFFECTS, $it['effect']))
            . sprintf('<td><input type="checkbox" name="%s" value="1"%s></td>', $f('hide_desktop'), checked($it['hide_desktop'], true, false))
            . sprintf('<td><input type="checkbox" name="%s" value="1"%s></td>', $f('hide_mobile'), checked($it['hide_mobile'], true, false))
            . '<td><button type="button" class="button-link-delete mbws-row-delete">Delete</button></td></tr>';
    };
    echo '<table class="widefat striped mbws-repeater"><thead><tr><th>Sort order</th><th>Contact type</th><th>Contact title</th><th>Link/URL</th><th>New tab</th><th>Color</th><th>Effect</th><th>Hide on desktop</th><th>Hide on mobile</th><th>Delete</th></tr></thead><tbody>';
    foreach ($s['items'] as $i => $it) {
        echo $row($i, $it);
    }
    echo '</tbody></table><template id="mbws-row-template">' . $row('__i__', []) . '</template>';
    echo '<p><button type="button" class="button mbws-row-add">+ Add contact</button></p></div>';
    submit_button('Save Changes');
    echo '</form></div>';
}

function mbws_chat_href($it) {
    $l = $it['link'];
    switch ($it['type']) {
        case 'phone': return 'tel:' . preg_replace('/[^0-9+]/', '', $l);
        case 'email': return 'mailto:' . $l;
        case 'zalo': return str_starts_with($l, 'http') ? $l : 'https://zalo.me/' . preg_replace('/\D/', '', $l);
        case 'messenger': return str_starts_with($l, 'http') ? $l : 'https://m.me/' . $l;
        default: return $l;
    }
}

add_action('wp_footer', function () {
    $s = mbws_chat_settings();
    if ($s['active'] !== 'yes' || !$s['items']) {
        return;
    }
    $icons = [
        'phone' => '<path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/>',
        'zalo' => '<path d="M12 2C6.5 2 2 5.9 2 10.7c0 2.7 1.4 5.1 3.7 6.7L5 22l4.4-2.4c.8.2 1.7.3 2.6.3 5.5 0 10-3.9 10-8.7S17.5 2 12 2z"/>',
        'messenger' => '<path d="M12 2C6.4 2 2 6.1 2 11.6c0 2.9 1.2 5.4 3.2 7.1V22l2.9-1.6c1.2.3 2.5.5 3.9.5 5.6 0 10-4.1 10-9.4S17.6 2 12 2zm1 12.6-2.6-2.7-5 2.7 5.5-5.8 2.6 2.7 4.9-2.7z"/>',
        'email' => '<path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5z"/>',
        'link' => '<path d="M10.6 13.4a1 1 0 0 0 1.4 0l4-4a3 3 0 0 0-4.2-4.2l-1.5 1.5 1.4 1.4 1.5-1.5a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 0 0 1.4zm2.8-2.8a1 1 0 0 0-1.4 0l-4 4a3 3 0 0 0 4.2 4.2l1.5-1.5-1.4-1.4-1.5 1.5a1 1 0 1 1-1.4-1.4l4-4a1 1 0 0 0 0-1.4z"/>',
    ];
    $side = $s['icon_position'] === 'left' ? 'left' : 'right';
    printf(
        '<style>.mbws-chat{position:fixed;%1$s:%2$dpx;bottom:%3$dpx;z-index:9999;display:flex;flex-direction:column;gap:10px}
.mbws-chat a{display:flex;align-items:center;gap:8px;text-decoration:none;color:%5$s}
.mbws-chat .ic{width:%6$dpx;height:%7$dpx;border-radius:50%%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.mbws-chat svg{width:55%%;height:55%%;fill:#fff}.mbws-chat .t{background:%4$s;padding:4px 10px;border-radius:14px;font-size:13px;box-shadow:0 1px 4px rgba(0,0,0,.15)}
.mbws-chat .shake .ic{animation:mbws-shake 1.2s infinite}.mbws-chat .pulse .ic{animation:mbws-pulse 1.5s infinite}
@keyframes mbws-shake{0%%,50%%,100%%{transform:rotate(0)}10%%,30%%{transform:rotate(-15deg)}20%%,40%%{transform:rotate(15deg)}}
@keyframes mbws-pulse{0%%{box-shadow:0 0 0 0 rgba(0,0,0,.35)}100%%{box-shadow:0 0 0 14px rgba(0,0,0,0)}}
@media(min-width:%8$dpx){.mbws-chat .hide-d{display:none}}
@media(max-width:%9$dpx){.mbws-chat .hide-m{display:none}%10$s}</style><div class="mbws-chat">',
        $side, $s['icon_pLeft'], $s['icon_pBottom'], esc_attr($s['bg_color']), esc_attr($s['text_color']),
        $s['icon_width'], $s['icon_height'], $s['min_screen'], $s['min_screen'] - 1,
        $s['mobile_layout'] === 'yes' ? '.mbws-chat{left:0;right:0;bottom:0;flex-direction:row;justify-content:space-around;gap:0;padding:6px;background:' . esc_attr($s['bg_color']) . ';box-shadow:0 -2px 6px rgba(0,0,0,.1)}.mbws-chat a{flex-direction:column;gap:2px;font-size:12px}.mbws-chat .t{box-shadow:none;padding:0;background:none}' : ''
    );
    foreach ($s['items'] as $it) {
        printf(
            '<a class="%s" href="%s"%s><span class="ic" style="background:%s"><svg viewBox="0 0 24 24">%s</svg></span>%s</a>',
            esc_attr(trim($it['effect'] . ($it['hide_desktop'] ? ' hide-d' : '') . ($it['hide_mobile'] ? ' hide-m' : ''))),
            esc_url(mbws_chat_href($it), ['http', 'https', 'tel', 'mailto']),
            $it['new_tab'] ? ' target="_blank" rel="noopener"' : '',
            esc_attr($it['color']),
            $icons[$it['type']] ?? $icons['link'],
            $it['title'] !== '' ? '<span class="t">' . esc_html($it['title']) . '</span>' : ''
        );
    }
    echo '</div>';
});

// ---------------------------------------------------------------- Embed code
const MBWS_SCRIPT_FIELDS = [
    'mbws_header_scripts' => 'Header Scripts',
    'mbws_footer_scripts' => 'Footer Scripts',
    'mbws_body_top_scripts' => 'Body Scripts - Top',
    'mbws_body_bottom_scripts' => 'Body Scripts - Bottom',
];

function mbws_page_scripts() {
    if (mbws_saving('mbws_scripts')) {
        if (!current_user_can('unfiltered_html')) {
            wp_die('Your account cannot save raw scripts.');
        }
        foreach (MBWS_SCRIPT_FIELDS as $k => $_) {
            update_option($k, wp_unslash($_POST[$k] ?? ''));
        }
        mbws_saved_notice();
    }
    echo '<div class="wrap mbws-wrap"><h1>Theme Settings</h1>';
    mbws_settings_tabs('mbws-option-scripts');
    echo '<form method="post"><div class="mbws-card"><table class="form-table">';
    wp_nonce_field('mbws_scripts', '_mbws_nonce');
    foreach (MBWS_SCRIPT_FIELDS as $k => $label) {
        mbws_field_row($label, sprintf('<textarea class="large-text code" rows="7" name="%s">%s</textarea>', $k, esc_textarea(get_option($k, ''))));
    }
    echo '</table></div>';
    submit_button('Save Changes');
    echo '</form></div>';
}

add_action('wp_head', fn() => print(get_option('mbws_header_scripts', '')), 99);
add_action('wp_body_open', fn() => print(get_option('mbws_body_top_scripts', '')), 1);
add_action('wp_footer', fn() => print(get_option('mbws_body_bottom_scripts', '')), 5);
add_action('wp_footer', fn() => print(get_option('mbws_footer_scripts', '')), 999);

// Primary/secondary colour from General → CSS variables (Flatsome reads --fs-color-*).
add_action('wp_head', function () {
    $p = get_option('mbws_color_primary');
    $s = get_option('mbws_color_secondary');
    if ($p || $s) {
        printf('<style>:root{%s%s}</style>',
            $p ? '--primary-color:' . esc_attr($p) . ';--fs-color-primary:' . esc_attr($p) . ';' : '',
            $s ? '--fs-color-secondary:' . esc_attr($s) . ';' : '');
    }
});

// ---------------------------------------------------------------- SMTP
function mbws_email_settings() {
    return wp_parse_args(get_option('mbws_email', []), [
        'from_name' => '', 'from_email' => '', 'recipient_emails' => '', 'smtp_is_custom' => '0',
        'smtp_host' => '', 'smtp_secure' => 'tls', 'smtp_port' => '587', 'smtp_username' => '', 'smtp_password' => '',
    ]);
}

function mbws_page_email() {
    $e = mbws_email_settings();
    if (mbws_saving('mbws_email')) {
        $p = wp_unslash($_POST);
        if (isset($p['send_test'])) {
            $to = sanitize_email($p['email_test'] ?? '');
            $ok = $to && wp_mail($to, 'SMTP Test - ' . get_bloginfo('name'), 'This is a test email sent from Website Settings → SMTP.');
            printf('<div class="notice notice-%s"><p>%s</p></div>', $ok ? 'success' : 'error',
                $ok ? 'Test email sent to ' . esc_html($to) . '.' : 'Test email failed. See Email Logs for the error.');
        } else {
            $e = [
                'from_name' => sanitize_text_field($p['from_name'] ?? ''),
                'from_email' => sanitize_email($p['from_email'] ?? ''),
                'recipient_emails' => implode(', ', array_filter(array_map('sanitize_email', explode(',', $p['recipient_emails'] ?? '')))),
                'smtp_is_custom' => ($p['smtp_is_custom'] ?? '0') === '1' ? '1' : '0',
                'smtp_host' => sanitize_text_field($p['smtp_host'] ?? ''),
                'smtp_secure' => in_array($p['smtp_secure'] ?? '', ['', 'ssl', 'tls'], true) ? $p['smtp_secure'] : 'tls',
                'smtp_port' => (string) absint($p['smtp_port'] ?? 587),
                'smtp_username' => sanitize_text_field($p['smtp_username'] ?? ''),
                // Blank password field = keep the stored one (it is never printed back into the page).
                'smtp_password' => ($p['smtp_password'] ?? '') !== '' ? $p['smtp_password'] : $e['smtp_password'],
            ];
            update_option('mbws_email', $e, false);
            mbws_saved_notice();
        }
    }
    $in = fn($k, $type = 'text') => sprintf('<input class="regular-text" type="%s" name="%s" value="%s">', $type, $k, esc_attr($e[$k]));
    echo '<div class="wrap mbws-wrap"><h1>Theme Settings</h1>';
    mbws_settings_tabs('mbws-option-email');
    echo '<form method="post"><div class="mbws-card"><h2>SMTP Settings</h2><table class="form-table">';
    wp_nonce_field('mbws_email', '_mbws_nonce');
    mbws_field_row('Sender Name:', $in('from_name'));
    mbws_field_row('Sent from email:', $in('from_email', 'email'));
    mbws_field_row('Recipient Email:', $in('recipient_emails') . '<p class="description">Contact form notifications go here. Separate several with commas.</p>');
    mbws_field_row('', sprintf('<label><input type="radio" name="smtp_is_custom" value="0"%s> Default server mail</label> <label><input type="radio" name="smtp_is_custom" value="1"%s> Custom SMTP</label>',
        checked($e['smtp_is_custom'], '0', false), checked($e['smtp_is_custom'], '1', false)));
    mbws_field_row('SMTP Host:', $in('smtp_host'));
    mbws_field_row('SMTP Security:', implode(' ', array_map(fn($v, $l) => sprintf('<label><input type="radio" name="smtp_secure" value="%s"%s> %s</label>', $v, checked($e['smtp_secure'], $v, false), $l), ['', 'ssl', 'tls'], ['None', 'SSL', 'TLS'])));
    mbws_field_row('SMTP Port:', $in('smtp_port'));
    mbws_field_row('SMTP Username:', $in('smtp_username'));
    mbws_field_row('SMTP Password:', '<input class="regular-text" type="password" name="smtp_password" autocomplete="new-password" placeholder="' . ($e['smtp_password'] ? '•••••••• (saved)' : '') . '">');
    mbws_field_row('SMTP Test:', '<input class="regular-text" type="email" name="email_test" placeholder="you@example.com"> <button class="button" name="send_test" value="1">Send</button>');
    echo '</table></div>';
    submit_button('Save Changes');
    echo '</form></div>';
}

add_action('phpmailer_init', function ($m) {
    $e = mbws_email_settings();
    if ($e['from_email']) {
        $m->setFrom($e['from_email'], $e['from_name'] ?: get_bloginfo('name'), false);
    }
    if ($e['smtp_is_custom'] === '1' && $e['smtp_host']) {
        $m->isSMTP();
        $m->Host = $e['smtp_host'];
        $m->Port = (int) $e['smtp_port'];
        $m->SMTPSecure = $e['smtp_secure'];
        $m->SMTPAuth = $e['smtp_username'] !== '';
        $m->Username = $e['smtp_username'];
        $m->Password = $e['smtp_password'];
    }
});

// CF7 notifications go to "Recipient Email" when set.
add_filter('wpcf7_mail_components', function ($c, $form, $mail) {
    $to = mbws_email_settings()['recipient_emails'];
    if ($to && $mail->name() === 'mail') {
        $c['recipient'] = $to;
    }
    return $c;
}, 10, 3);

// ---------------------------------------------------------------- Thông báo
const MBWS_NOTIFY = [
    'mbws_disable_email_new_user' => 'Đăng ký người dùng mới:',
    'mbws_disable_email_password_change' => 'Thay đổi mật khẩu:',
    'mbws_disable_email_admin_email_change' => 'Thay đổi email quản trị:',
    'mbws_disable_email_auto_update' => 'Tự động cập nhật:',
    'mbws_disable_email_comment' => 'Thông báo bình luận:',
    'mbws_disable_email_recovery_mode' => 'Chế độ khôi phục (Recovery Mode):',
    'mbws_disable_wsal_email' => 'Gửi email nhật ký hoạt động WP:',
];

function mbws_page_notification() {
    if (mbws_saving('mbws_notify')) {
        foreach (MBWS_NOTIFY as $k => $_) {
            update_option($k, empty($_POST[$k]) ? '0' : '1');
        }
        mbws_saved_notice();
    }
    echo '<div class="wrap mbws-wrap"><h1>Theme Settings</h1>';
    mbws_settings_tabs('mbws-option-notification');
    echo '<form method="post"><div class="mbws-card"><h2>Cài đặt thông báo</h2><p>Tick để <strong>tắt</strong> email thông báo tương ứng.</p><table class="form-table">';
    wp_nonce_field('mbws_notify', '_mbws_nonce');
    foreach (MBWS_NOTIFY as $k => $label) {
        mbws_field_row($label, sprintf('<label><input type="checkbox" name="%s" value="1"%s> Tắt</label>', $k, checked(get_option($k), '1', false)));
    }
    echo '</table></div>';
    submit_button('Save Changes');
    echo '</form></div>';
}

$mbws_off = fn($k) => get_option($k) === '1';
// Some core notices can't be cancelled by a filter; mark them and drop them in pre_wp_mail instead.
$mbws_suppress_next = function ($email) {
    $GLOBALS['mbws_suppress_mail'] = true;
    return $email;
};
add_filter('pre_wp_mail', function ($r) {
    if (!empty($GLOBALS['mbws_suppress_mail'])) {
        $GLOBALS['mbws_suppress_mail'] = false;
        return true;
    }
    return $r;
});
if ($mbws_off('mbws_disable_email_new_user')) {
    add_filter('wp_send_new_user_notification_to_admin', '__return_false');
}
if ($mbws_off('mbws_disable_email_password_change')) {
    add_filter('send_password_change_email', '__return_false');
    add_filter('wp_password_change_notification_email', $mbws_suppress_next);
}
if ($mbws_off('mbws_disable_email_admin_email_change')) {
    add_filter('send_site_admin_email_change_email', '__return_false');
    add_filter('send_email_change_email', '__return_false');
}
if ($mbws_off('mbws_disable_email_auto_update')) {
    foreach (['auto_core_update_send_email', 'auto_plugin_update_send_email', 'auto_theme_update_send_email'] as $f) {
        add_filter($f, '__return_false');
    }
}
if ($mbws_off('mbws_disable_email_comment')) {
    add_filter('notify_post_author', '__return_false');
    add_filter('notify_moderator', '__return_false');
}
if ($mbws_off('mbws_disable_email_recovery_mode')) {
    add_filter('recovery_mode_email', $mbws_suppress_next);
}
if ($mbws_off('mbws_disable_wsal_email')) {
    add_filter('wsal_disable_emails', '__return_true');
}
