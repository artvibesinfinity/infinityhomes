<?php
/**
 * Plugin Name: Infinity Admin
 * Description: MatBao WS–style admin for infinityhomes: menu layout, dashboard, Website Settings, Statistical logs, contact button, embed code, SMTP, auto publish.
 */
defined('ABSPATH') || exit;

foreach (glob(__DIR__ . '/infinity-admin/*.php') as $file) {
    require_once $file;
}
