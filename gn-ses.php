<?php
/*
 * Plugin Name: Gnaritas Amazon SES
 * Version: 0.1.0
 * Description: Sends WordPress email using Amazon SES and automatically handles bounces and complaints.
 * Author: Gnaritas, Inc.
 * Author URI: http://gnaritas.com
 * 
 */

if ( ! defined( 'ABSPATH' ) ) exit();

require_once("vendor/autoload.php");
require_once("classes/gn-wp-list-table.php");
require_once("classes/voce-settings-api/voce-settings-api.php");
require_once("classes/gn_BasePluginDB.class.php");
require_once("classes/gn_CSVWriter.class.php");
require_once("classes/SesData.php");
require_once("classes/Ses.php");
require_once("classes/GenericAdminPage.php");
require_once("classes/NotificationListTable.php");



$gnses = new gnaritas\ses\Ses();

add_action("init", array(&$gnses, 'wpInit'));

register_activation_hook(__FILE__, array(&$gnses, "activate"));
register_deactivation_hook(__FILE__, array(&$gnses, "deactivate"));
