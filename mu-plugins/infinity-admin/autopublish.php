<?php
// Posts → Auto Publish Posts: upload .docx files, each becomes a post (headings, bold/italic, links, lists, tables, images).
defined('ABSPATH') || exit;

function mbws_page_autopublish() {
    $cats = get_categories(['hide_empty' => false]);
    ?>
    <div class="wrap mbws-wrap"><h1>Automatic Post Publishing</h1>
    <div class="mbws-card"><h2>1. Upload attachment</h2>
        <input type="file" id="mbws-docx" accept=".docx" multiple hidden>
        <button type="button" class="button" onclick="document.getElementById('mbws-docx').click()">Select file</button>
        <span id="mbws-docx-names" class="description">Chọn một hoặc nhiều file Word (.docx). Mỗi file thành một bài viết.</span>
    </div>
    <div class="mbws-card"><h2>2. Configuration</h2><table class="form-table">
        <tr><th>Categories</th><td><select id="mbws-cats" multiple size="6" style="min-width:260px">
            <?php foreach ($cats as $c) printf('<option value="%d">%s</option>', $c->term_id, esc_html($c->name)); ?>
        </select></td></tr>
        <tr><th>Status</th><td><select id="mbws-status"><option value="draft">Draft</option><option value="publish">Publish</option></select></td></tr>
        <tr><th>Tags</th><td><input id="mbws-tags" class="regular-text" placeholder="e.g. seo, content, marketing"></td></tr>
        <tr><th>Options</th><td><label><input type="checkbox" id="mbws-thumb" checked> Set the first image as featured image</label></td></tr>
    </table></div>
    <div class="mbws-card"><h2>3. Progress</h2>
        <p><button type="button" class="button button-primary" id="mbws-start">Start Publishing</button>
        <button type="button" class="button" id="mbws-pause">Pause</button>
        <button type="button" class="button" id="mbws-resume">Resume</button>
        <button type="button" class="button" id="mbws-cancel">Cancel</button>
        <button type="button" class="button-link" id="mbws-clear">Clear log</button></p>
        <div class="mbws-log" id="mbws-log"></div>
    </div></div>
    <script>
    (function () {
        var queue = [], paused = false, running = false, nonce = <?php echo wp_json_encode(wp_create_nonce('mbws_word')); ?>;
        var $ = function (id) { return document.getElementById(id); };
        function log(msg) { var l = $('mbws-log'); l.innerHTML += msg + '<br>'; l.scrollTop = l.scrollHeight; }
        $('mbws-docx').onchange = function () { $('mbws-docx-names').textContent = Array.from(this.files).map(function (f) { return f.name; }).join(', '); };
        function next() {
            if (paused || !queue.length) { running = false; if (!queue.length) log('— Done.'); return; }
            running = true;
            var f = queue.shift(), fd = new FormData();
            fd.append('action', 'mbws_word_to_post'); fd.append('_ajax_nonce', nonce); fd.append('file', f);
            fd.append('status', $('mbws-status').value); fd.append('tags', $('mbws-tags').value);
            fd.append('thumbnail', $('mbws-thumb').checked ? '1' : '');
            Array.from($('mbws-cats').selectedOptions).forEach(function (o) { fd.append('cats[]', o.value); });
            log('⏳ ' + f.name + ' …');
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
                log(r.success ? '✅ ' + f.name + ' → <a href="' + r.data.edit + '" target="_blank">' + r.data.title + '</a>' : '❌ ' + f.name + ': ' + (r.data || 'error'));
            }).catch(function (e) { log('❌ ' + f.name + ': ' + e); }).then(next);
        }
        $('mbws-start').onclick = function () {
            queue = Array.from($('mbws-docx').files);
            if (!queue.length) { log('Chưa chọn file.'); return; }
            paused = false; if (!running) next();
        };
        $('mbws-pause').onclick = function () { paused = true; log('⏸ Paused'); };
        $('mbws-resume').onclick = function () { paused = false; log('▶ Resume'); if (!running) next(); };
        $('mbws-cancel').onclick = function () { queue = []; log('✖ Cancelled'); };
        $('mbws-clear').onclick = function () { $('mbws-log').innerHTML = ''; };
    })();
    </script>
    <?php
}

add_action('wp_ajax_mbws_word_to_post', function () {
    check_ajax_referer('mbws_word');
    if (!current_user_can('publish_posts')) {
        wp_send_json_error('Not allowed');
    }
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'docx') {
        wp_send_json_error('Only .docx files are supported');
    }
    try {
        [$title, $html, $images] = mbws_docx_to_html($f['tmp_name']);
    } catch (Throwable $e) {
        wp_send_json_error($e->getMessage());
    }
    $title = $title ?: sanitize_text_field(pathinfo($f['name'], PATHINFO_FILENAME));
    $id = wp_insert_post([
        'post_title' => $title,
        'post_content' => '',
        'post_status' => ($_POST['status'] ?? '') === 'publish' ? 'publish' : 'draft',
        'post_category' => array_map('absint', (array) ($_POST['cats'] ?? [])),
        'tags_input' => array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['tags'] ?? ''))))),
    ], true);
    if (is_wp_error($id)) {
        wp_send_json_error($id->get_error_message());
    }
    // Images: upload into the media library and swap the placeholders for real URLs.
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $first = 0;
    foreach ($images as $token => [$name, $bytes]) {
        $up = wp_upload_bits(wp_unique_filename(wp_get_upload_dir()['path'], $name), null, $bytes);
        if ($up['error']) {
            $html = str_replace($token, '', $html);
            continue;
        }
        $att = wp_insert_attachment(['post_mime_type' => wp_check_filetype($up['file'])['type'], 'post_title' => $title, 'post_status' => 'inherit'], $up['file'], $id);
        wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
        $first = $first ?: $att;
        $html = str_replace($token, esc_url($up['url']), $html);
    }
    wp_update_post(['ID' => $id, 'post_content' => wp_kses_post($html)]);
    if ($first && !empty($_POST['thumbnail'])) {
        set_post_thumbnail($id, $first);
    }
    if (function_exists('pll_set_post_language') && function_exists('pll_default_language')) {
        pll_set_post_language($id, pll_default_language());
    }
    wp_send_json_success(['id' => $id, 'title' => esc_html($title), 'edit' => get_edit_post_link($id, 'raw')]);
});

/** @return array{0:string,1:string,2:array<string,array{0:string,1:string}>} [title, html, images keyed by placeholder] */
function mbws_docx_to_html($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
        throw new RuntimeException('Not a valid .docx file');
    }
    $rels = [];
    if ($r = $zip->getFromName('word/_rels/document.xml.rels')) {
        $rd = new DOMDocument();
        $rd->loadXML($r);
        foreach ($rd->getElementsByTagName('Relationship') as $rel) {
            $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NONET);
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $images = [];
    $title = '';
    $runs = function (DOMNode $p) use ($xp, $rels, $zip, &$images) {
        $out = '';
        foreach ($xp->query('.//w:r', $p) as $n) {
            $text = '';
            foreach ($n->childNodes as $c) {
                if ($c->localName === 't') $text .= esc_html($c->textContent);
                elseif ($c->localName === 'tab') $text .= ' ';
                elseif ($c->localName === 'br') $text .= '<br>';
                elseif ($c->localName === 'drawing') {
                    $blip = $xp->query('.//a:blip/@r:embed', $c)->item(0);
                    $target = $blip ? ($rels[$blip->value] ?? '') : '';
                    $bytes = $target ? $zip->getFromName('word/' . ltrim($target, '/')) : false;
                    if ($bytes !== false) {
                        $token = '%%MBWSIMG' . count($images) . '%%';
                        $images[$token] = [basename($target), $bytes];
                        $text .= '<img src="' . $token . '" alt="">';
                    }
                }
            }
            if ($text === '') continue;
            $on = fn($tag) => ($e = $xp->query("w:rPr/w:$tag", $n)->item(0)) && !in_array($e->getAttribute('w:val'), ['0', 'false', 'none'], true);
            if ($on('b')) $text = "<strong>$text</strong>";
            if ($on('i')) $text = "<em>$text</em>";
            if ($on('u')) $text = "<u>$text</u>";
            $link = $n->parentNode->localName === 'hyperlink' ? ($rels[$n->parentNode->getAttribute('r:id')] ?? '') : '';
            $out .= $link ? '<a href="' . esc_url($link) . '">' . $text . '</a>' : $text;
        }
        return $out;
    };

    $html = '';
    $list_open = false;
    foreach ($xp->query('/w:document/w:body/*') as $node) {
        if ($node->localName === 'tbl') {
            if ($list_open) { $html .= "</ul>\n"; $list_open = false; }
            $html .= "<table>\n";
            foreach ($xp->query('w:tr', $node) as $tr) {
                $html .= '<tr>';
                foreach ($xp->query('w:tc', $tr) as $tc) {
                    $cell = [];
                    foreach ($xp->query('w:p', $tc) as $p) $cell[] = $runs($p);
                    $html .= '<td>' . implode('<br>', array_filter($cell)) . '</td>';
                }
                $html .= "</tr>\n";
            }
            $html .= "</table>\n";
            continue;
        }
        if ($node->localName !== 'p') continue;
        $inner = $runs($node);
        $style = $xp->query('w:pPr/w:pStyle/@w:val', $node)->item(0)?->value ?? '';
        $is_list = $xp->query('w:pPr/w:numPr', $node)->length > 0 || stripos($style, 'List') === 0;
        if (!$is_list && $list_open) { $html .= "</ul>\n"; $list_open = false; }
        if (trim(strip_tags($inner, '<img>')) === '') continue;
        if (!$title && preg_match('/^(Title|Heading1|Heading 1|1)$/i', $style)) {
            $title = wp_strip_all_tags($inner);
            continue;
        }
        if ($is_list) {
            if (!$list_open) { $html .= "<ul>\n"; $list_open = true; }
            $html .= "<li>$inner</li>\n";
        } elseif (preg_match('/^Heading ?([1-6])$/i', $style, $m)) {
            $html .= "<h{$m[1]}>$inner</h{$m[1]}>\n";
        } else {
            $html .= "<p>$inner</p>\n";
        }
    }
    if ($list_open) $html .= "</ul>\n";
    $zip->close();
    return [$title, $html, $images];
}
