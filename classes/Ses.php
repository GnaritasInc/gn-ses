<?php

namespace gnaritas\ses;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Aws\Sns\Exception;

class Ses
{
    public $prefix = "gnses";
    public $adminCapability = "manage_options";    
    public $adminActionKey = "gnses_action";
    public $nonceKey = "gnses_nonce";
    public $optionsKey = "gnses_options";
    
    protected $optionDefaults = null;

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
            "main"=>array("title"=>"SES Settings", "menu_title"=>"Amazon SES", "action"=>"update_settings"),
            "test-email"=>array("title"=>"Test Email", "parent"=>"main", "action"=>"test_email"),
            "notifications"=>array("title"=>"SNS Notifications", "parent"=>"main")
        ));

        if (is_admin() && isset($_POST[$this->adminActionKey])) {
            $this->handleAdminPost();
        }

        add_action("wp_ajax_nopriv_sns_notify", array(&$this, "handleSNSNotification"));       
    }

    function handleSNSNotification () {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("Not a POST request.");
            $this->ajaxExit(405);
        }
        try {
            $message = Message::fromRawPostData();
            $validator = new MessageValidator();
            $validator->validate($message);

            $this->processSnsMessage($message);
        }
        catch (InvalidSnsMessageException $e) {
            error_log("Invalid SNS message: ".$e->getMessage());
            $this->ajaxExit(404);
        }
        catch (Exception $e) {
            error_log("Error processing SNS notification: ".$e->getMessage);
            $this->ajaxExit(500);
        }
        
        $this->ajaxExit(200);
        
    }

    function processSnsMessage ($message) {
        $messageType = $message["Type"];
        $method = "handle{$messageType}";
        if (method_exists($this, $method)) {
            $this->$method($message);
        }
        else {
            throw new Exception("Unsupported SNS message type '$messageType'");
        }
    }

    function handleNotification ($message) {
        $messageData = json_decode($message['Message']);
        $notificationType = $messageData->notificationType;
        if ($notificationType=="Bounce") {
            $this->processBounce($messageData->bounce);
        }
        elseif ($notificationType == "Complaint") {
            $this->processComplaint($messageData->complaint);
        }
        else {
            throw new Exception("Unsupported SES notification '$notificationType'");
        }
    }

    function processBounce ($bounce) {
        $this->data->logBounce($bounce);
        do_action("gnses_bounce", $bounce);
    }

    function processComplaint ($complaint) {
        $this->data->LogComplaint($complaint);
        do_action("gnses_complaint", $complaint);
    }

    function handleUnsubscribeConfirmation ($message) {
        error_log("Unsubscribed from ".$message['TopicArn']);
        file_get_contents($message['SubscribeURL']);
    }

    function handleSubscriptionConfirmation ($message) {
        error_log("Subscribed to ".$message['TopicArn']);
        file_get_contents($message['SubscribeURL']);
    }

    function ajaxExit ($status) {
        status_header($status);
        exit();
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

    function test_email () {
        $formData = array_intersect_key($_POST, array_fill_keys(array("email", "subject", "message"), ""));
        if ($errors = $this->validateEmailInput($formData)) {
            $this->errors = $errors;
            return;
        }

        $this->setMailerCallback();
        add_action("wp_mail_failed", array(&$this, "setEmailError"));

        if ($result = wp_mail($formData['email'], $formData['subject'], $formData['message'])) {
            $this->msg = "Test email sent successfully.";
        }
    }

    function validateEmailInput ($data) {
        $errors = array();
        foreach ($data as $key=>$value) {            
            if ($key=='email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { 
                $errors[] = "Invalid email address.";
            }
            elseif (!$this->hasInput($value)) {
                $errors[] = ucfirst($key)." is required.";
            }
        }

        return $errors;
    }

    function setEmailError ($error) {
        $this->errors[] = "Email failed: ".$error->get_error_message();
    }

    function update_settings () {
        $input = $_POST[$this->optionsKey];
        $newOptions = array_merge(array("suppress_bounce"=>0, "remove_tables"=>0), array_intersect_key($input, $this->getOptionDefaults()));
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
            "from_address"=>array("callback"=>"isEmail", "msg"=>"From address must be a well-formed email address."),
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

    function isEmail ($str) {
        return is_email($str);
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
            if (array_key_exists("action", $data)) {
                $adminPage->action = $data["action"];
            }
        }

        add_filter("gn_admin_page_data_gnses-main", array(&$this, 'mainPageData'));
    }

    function getOptions () {
        $defaults = $this->getOptionDefaults();
        $savedOptions = get_option($this->optionsKey, $defaults);

        return array_merge($defaults, $savedOptions);
    }

    function getOption ($key) {
        $options = $this->getOptions();
        return $options[$key];
    }

    function getOptionDefaults () {
        if (is_null($this->optionDefaults)) {
            $this->optionDefaults = array(
                "host"=>"",
                "port"=>"",
                "username"=>"",
                "password"=>"",
                "suppress_bounce"=>1,
                "remove_tables"=>0,
                "from_address"=>get_option('admin_email'),
                "from_name"=>""
            );
        }

        return $this->optionDefaults;
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

        $phpmailer->setFrom(get_option("admin_email"), "Test", false );

        if ($options["suppress_bounce"]) {
            $this->checkRecipients($phpmailer);
        }
    }

    function checkRecipients ($phpmailer) {
        $recipients = array(
            "TO"=>$phpmailer->getToAddresses(),
            "CC"=>$phpmailer->getCcAddresses(),
            "BCC"=>$phpmailer->getBccAddresses()
        );

        $phpmailer->clearAllRecipients();        
        
        foreach ($recipients as $type=>$addresses) {
            foreach($addresses as $addressInfo) {
                $addMethod = ($type == "TO") ? "addAddress" : "add{$type}";
                list($address, $name) = $addressInfo;               
                if ($this->data->isSuppressed($address)) {
                    error_log("gn-ses: Removing suppressed address '$address'");
                }
                else {
                    $phpmailer->$addMethod($address, $name);
                }
            }
        }

    }
    
}


