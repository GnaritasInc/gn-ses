<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option("gnses_options");

global $wpdb;
$wpdb->query("drop table if exists gnses_notification");
