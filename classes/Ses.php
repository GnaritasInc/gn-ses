<?php

namespace gnaritas\ses;

class Ses
{
    protected $adminCapability = "manage_options";
    protected $adminMenuSlug = "gnses";

    function __construct () {
        $this->data = new SesData();
        $this->homeDir = dirname(dirname(__FILE__));
        $this->includeDir = $this->homeDir . "/includes";
    }

    function wpInit () {
        add_action('admin_menu', array(&$this, 'adminMenu'));
        add_action('admin_init', array(&$this, 'adminInit'));
    }

    function adminMenu () {
        
        add_menu_page("SES Settings", "SES Settings", $this->adminCapability, $this->adminMenuSlug."-main", array(&$this, 'adminPage'));
        add_submenu_page($this->adminMenuSlug."-main", "SNS Notificaitons", "SNS Notifications", $this->adminCapability, $this->adminMenuSlug."-notifications", array(&$this, 'adminPage'));
    }

    function adminPage () {
        $slug = preg_replace('/^'.$this->adminMenuSlug.'/', 'admin', trim($_GET['page']));
        $filename = "$slug.php";       

        if (file_exists($this->includeDir."/$filename")) {
            $this->includeFile($filename);
        }
    }

    function adminInit () {
        $this->registerOptions();
    }

    function registerOptions () {
        register_setting("gnses_options", "gnses_options", array(&$this, 'validateOptions'));
        add_settings_section("gnses_main", "SES Settings", array(&$this, 'mainSettingsSection'), "gnses-main");
        add_settings_field("gnses_host", "Email Host", array(&$this, 'hostField'), "gnses-main", "gnses_main");
    }

    function hostField () {
        $options = get_option("gnses_options");
        echo "<input id='gnses_host' name='gnses_options[host]' size='40' type='text' value='{$options['host']}' />";
    }

    function mainSettingsSection () {
        echo "<p>SES Main Settings</p>";
    }

    function validateOptions ($options) {
        return $options;
    }

    function includeFile ($fileName, $context=array()) {
        include($this->includeDir . "/$fileName");
    }
}


