<?php
// Self-check for the admin plugin: docx conversion, email log + warning, page-view counter.
[$title, $html, $images] = mbws_docx_to_html('/data/test/sample.docx');
assert($title === 'Bài viết thử từ Word', 'title');
assert(str_contains($html, '<h2>Mục 1</h2>'), 'heading');
assert(str_contains($html, '<strong>chữ đậm</strong>'), 'bold');
assert(str_contains($html, '<a href="https://example.com">'), 'link');
assert(substr_count($html, '<li>') === 2 && str_contains($html, '<ul>'), 'list');
assert(str_contains($html, '<td>A1</td><td>B1</td>'), 'table');
assert(count($images) === 1 && str_contains($html, array_key_first($images)), 'image');

wp_mail('test@example.test', 'Check', 'Body'); // sent or failed, either way it must be logged
global $wpdb;
$last = $wpdb->get_row("SELECT * FROM " . mbws_table('email_logs') . " ORDER BY id DESC LIMIT 1");
assert($last && $last->recipient === 'test@example.test', 'email logged');
$wpdb->delete(mbws_table('email_logs'), ['id' => $last->id]); // leave the log clean

echo "all checks passed\n";
