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
        
        $this->settings->add_page("SES Settings", "Amazon SES", $this->adminMenuSlug."-main")
            ->add_group("Email Settings", "gnses_email")
                ->add_setting("Host", "host")
                ->group->add_setting("Port", "port")
                ->group->add_setting("Username", "username")
                ->group->add_setting("Password", "password");

       $this->setupAdminPages(array(
            "test-email"=>"Test Email",
            "notifications"=>"SNS Notifications"
        ));
        
    }

    function setupAdminPages ($pageData) {
        foreach($pageData as $key=>$title) {
            $adminPage = new GenericAdminPage($title, $title, $this->adminMenuSlug."-{$key}", $this->adminCapability, '', $this->adminMenuSlug."-main-page");
            $adminPage->template = $this->includeDir . "/admin-{$key}.php";
        }
    }
    
}


