<?php

namespace gnaritas\ses;

class Ses
{
    public $prefix = "gnses";
    public $adminCapability = "manage_options";    
    public $adminActionKey = "gnses_action";
    public $nonceKey = "gnses_nonce";
    public $optionsKey = "gnses_options";
    public $optionDefaults = array(
        "host"=>"",
        "port"=>"",
        "username"=>"",
        "password"=>"",
        "suppress_bounce"=>1,
        "remove_tables"=>0
    );
    public $errors = array();

    protected $adminActions = array("update_settings", "test_email");
   

    function __construct () {
        $this->data = new SesData();        
        $this->homeDir = dirname(dirname(__FILE__));
        $this->includeDir = $this->homeDir . "/includes";
    }

    function activate () {
        $this->data->insureTables();
    }

    function deactivate () {
        if ($this->getOption("remove_tables")) {
            $this->data->dropTables();
        }
    }

    function wpInit () {
       $this->setupAdminPages(array(
            "main"=>array("title"=>"SES Settings", "menu_title"=>"Amazon SES"),
            "test-email"=>array("title"=>"Test Email", "parent"=>"main"),
            "notifications"=>array("title"=>"SNS Notifications", "parent"=>"main")
        ));

        if (is_admin() && isset($_POST[$this->adminActionKey])) {
            $this->handleAdminPost();
        }        
    }

    function handleAdminPost () {
        $action = trim($_POST[$this->adminActionKey]);
        if (!wp_verify_nonce($_POST[$this->nonceKey], $action) || !current_user_can($this->adminCapability)) {
            wp_die("Unauthorized");
        }

        if (in_array($action, $this->adminActions) && method_exists($this, $action)) {
            $this->$action();
        }
    }

    function update_settings () {
        $input = $_POST[$this->optionsKey];
        $newOptions = array_merge(array("suppress_bounce"=>0, "remove_tables"=>0), array_intersect_key($input, $this->optionDefaults));
        if ($errors = $this->validateOptions($newOptions)) {
            $this->errors = $errors;
            return;
        }
        else {
            update_option($this->optionsKey, $newOptions);
            $this->msg = "Settings updated.";
        }
    }

    function validateOptions ($options) {
        $errors = array();

        $fields = array(
            "host"=>array("callback"=>"isHostname", "msg"=>"Host must be a well-formed internet host name."),
            "port"=>array("callback"=>"isPosint", "msg"=>"Port must be a positive integer."),
            "username"=>array("msg"=>"Username is required."),
            "password"=>array("msg"=>"Password is required.")
        );

        foreach ($fields as $key=>$fieldInfo) {
            $callback = array_key_exists("callback", $fieldInfo) ? $fieldInfo['callback'] : "hasInput";
            if (!$this->$callback($options[$key])) {
                $errors[] = $fieldInfo['msg'];
            }
        }

        return $errors;
    }

    function isHostname ($str) {
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $str);
    }

    function isPosint ($str) {
        return preg_match('/^[1-9][0-9]*$/', $str);
    }

    function hasInput ($str) {
        return strlen(trim($str)) ? true : false;
    }

    function displayMessages () {
        foreach ($this->errors as $error) {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', htmlspecialchars($error));
        }

        if (!$this->errors && $this->msg) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', htmlspecialchars($this->msg));
        }
    }

    function setupAdminPages ($pageData) {
        foreach($pageData as $key=>$data) {
            
            $title = $data['title'];
            $menuTitle = array_key_exists('menu_title', $data) ? $data['menu_title'] : $title;
            $parent = array_key_exists('parent', $data) ? $this->prefix . '-'. $data['parent'] : '';
            
            $adminPage = new GenericAdminPage($title, $menuTitle, $this->prefix."-{$key}", $this->adminCapability, '', $parent);
            $adminPage->template = $this->includeDir . "/admin-{$key}.php";
            $adminPage->plugin = &$this;
        }

        add_filter("gn_admin_page_data_gnses-main", array(&$this, 'mainPageData'));
    }

    function getOptions () {
        return get_option($this->optionsKey, $this->optionDefaults);
    }

    function getOption ($key) {
        $options = get_option($this->optionsKey, $this->optionDefaults);
        return $options[$key];
    }

    function mainPageData ($data) {
        $data = $this->errors ? $_POST[$this->optionsKey] : $this->getOptions();
        return $data;
    }

    function setMailerCallback () {
        add_action('phpmailer_init', array(&$this, 'setMailerConfig'));
    }

    function setMailerConfig ($phpmailer) {
        $options = $this->getOptions();

        $phpmailer->isSMTP();     
        $phpmailer->Host = $options['host'];
        $phpmailer->SMTPAuth = true; 
        $phpmailer->Port = $options['port'];
        $phpmailer->Username = $options['username'];
        $phpmailer->Password = $options['password'];
        $phpmailer->SMTPSecure = "tls";
    }   
    
}


