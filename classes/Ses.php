<?php

namespace gnaritas\ses;

class Ses
{
    protected $adminCapability = "manage_options";
    protected $adminMenuSlug = "gnses";
    protected $settings;

    function __construct () {
        $this->data = new SesData();
        $this->settings = \Voce_Settings_API::getInstance();
        $this->homeDir = dirname(dirname(__FILE__));
        $this->includeDir = $this->homeDir . "/includes";
    }

    function wpInit () {        
        $required = array(&$this, "requireInput");
        $absint = array(&$this, "checkAbsint");

        $this->settings->add_page("SES Settings", "Amazon SES", $this->adminMenuSlug."-main")
            ->add_group("Email Settings", "gnses_email")
                ->add_setting("Host", "host", array("sanitize_callbacks"=>array($required)))
                ->group->add_setting("Port", "port", array("sanitize_callbacks"=>array($absint)))
                ->group->add_setting("Username", "username", array("sanitize_callbacks"=>array($required)))
                ->group->add_setting("Password", "password", array("sanitize_callbacks"=>array($required)))
            ->group->page->add_group("Bounce Handling", "gnses_bounce")
                ->add_setting("Suppress email to bounced/compained recipients?", "suppress_bounce", array("default_value"=>"on", "display_callback"=>"vs_display_checkbox"))
            ->group->page->add_group("Deactivation", "gnses_deactivation")
                ->add_setting("Remove notification logs on deactivation?", "remove_tables", array("display_callback"=>"vs_display_checkbox"));

       $this->setupAdminPages(array(
            "test-email"=>"Test Email",
            "notifications"=>"SNS Notifications"
        ));
        
    }

    function checkAbsint ($value, $setting, $args) {
        $newValue = absint($value);
       if (strval($newValue) !== $value) {
            $setting->add_error( sprintf('"%s" must be a non-negative integer.', $setting->title));
            return $this->settings->get_setting($setting->setting_key, $setting->group->group_key);
        }

        return $value;
    }

    function requireInput ($value, $setting, $args) {
        if (!strlen(trim($value))) {
            $setting->add_error( sprintf('"%s" is required.', $setting->title));
            return $this->settings->get_setting($setting->setting_key, $setting->group->group_key);
        }

        return $value;
    }

    function setupAdminPages ($pageData) {
        foreach($pageData as $key=>$title) {
            $adminPage = new GenericAdminPage($title, $title, $this->adminMenuSlug."-{$key}", $this->adminCapability, '', $this->adminMenuSlug."-main-page");
            $adminPage->template = $this->includeDir . "/admin-{$key}.php";
        }
    }
    
}


