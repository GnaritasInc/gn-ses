<?php
/*
 * Plugin Name: Gnaritas Amazon SES
 * Description: Sends WordPress email using Amazon SES and automatically handles bounces and complaints.
 * Author: Gnaritas, Inc.
 * 
 */

require_once("classes/gn_PluginDB.class.php");
require_once("classes/SesData.php");
require_once("classes/Ses.php");

$gnses = new gnaritas\ses\Ses();

add_action("init", array(&$gnses, 'wpInit'));
