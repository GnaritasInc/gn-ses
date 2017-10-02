<?php
/*
 * Plugin Name: Gnaritas Amazon SES
 * Description: Sends WordPress email using Amazon SES and automatically handles bounces and complaints.
 * Author: Gnaritas, Inc.
 * 
 */

require_once("vendor/autoload.php");
require_once("classes/gn_PluginDB.class.php");
require_once("classes/SesData.php");
require_once("classes/Ses.php");
require_once("classes/GenericAdminPage.php");



$gnses = new gnaritas\ses\Ses();

add_action("init", array(&$gnses, 'wpInit'));

register_activation_hook(__FILE__, array(&$gnses, "activate"));
register_deactivation_hook(__FILE__, array(&$gnses, "deactivate"));
