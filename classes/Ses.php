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
    public $exportActionText = "Export as CSV";
    
    protected $optionDefaults = null;

    public $errors = array();

    protected $adminActions = array("update_settings", "test_email");

    protected $awsOptions = null;
    protected $sdkFactory = null;
    protected $awsClients = array();
    protected $emailOptions = null;
   

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

        if (is_admin()) {
            if (isset($_POST[$this->adminActionKey])) {
                $this->handleAdminPost();
            }
            elseif ($_GET[$this->adminActionKey] == $this->exportActionText) {
                $this->doCSVExport();
            }
        }

        add_action("wp_ajax_nopriv_sns_notify", array(&$this, "handleSNSNotification"));

        if ($this->getOption("_smtp_ok")) {
            $this->setMailCallbacks();
        }

        add_action("admin_notices", array(&$this, "doAdminNotices"));       
    }

    function doAdminNotices () {
        global $pagenow;
        if (!($pagenow=="index.php" || $this->onSettingsPage())) {
            return;
        }

        if ($this->getOption("_smtp_ok")) {
            $this->adminNotice("Sending email through Amazon SES.");
        }
        else {
            $this->adminNotice("Unable to send email through Amazon SES. Please update your settings.", "error");
        }

        if ($this->getOption("_topic_arn")) {
            $this->adminNotice("Handling bounces and complaints through Amazon SNS and suppressing email to bounced/complained addresses.");
        }
        else {
            $this->adminNotice("Bounce and complaint notifications not handled through Amazon SNS.", "warning");
        }
    }

    function onSettingsPage () {
        return is_admin() && $_GET['page'] == "gnses-main";
    }

    function adminNotice ($text, $type="info") {
        $prefix = $this->onSettingsPage() ? "" : "<b>Amazon SES</b>: ";
        echo "<div class='notice notice-{$type} is-dismissible'><p>{$prefix}$text</p></div>";
    }

    function setEmailOptions ($options=array()) {
       $fields = array('host', 'port', 'username', '_smtp_password', 'from_address', 'from_name', 'suppress_bounce');
       $defaults = array_intersect_key($this->getOptions(), array_fill_keys($fields, ''));

       $this->emailOptions = array_merge($defaults, array_intersect_key($options, $defaults));      
    }

    function getEmailOptions () {
        if (is_null($this->emailOptions)) {
            $this->setEmailOptions();
        }

        return $this->emailOptions;
    }

    function getEmailOption ($key, $default=null) {
        $options = $this->getEmailOptions();
        $value = $options[$key];

        return strlen($value) ? $value : $default;
    }

    function setAwsOptions ($options=array()) {
        $defaults = array(
            "version"=>"latest",
            "region"=>$this->getOption("ses_region"),
            "credentials"=>array(
                "key"=>$this->getOption("username"),
                "secret"=>$this->getOption("password")
            )
        );

        if (is_null($this->awsOptions)) {
            $this->awsOptions = array_merge($defaults, $options);            
        }
    }

    function getAwsOptions () {
        if (is_null($this->awsOptions)) {
            $this->setAwsOptions();
        }

        return $this->awsOptions;
    }

    function getSdkFactory () {       
        
        if (is_null($this->sdkFactory)) {
            $this->sdkFactory = new \Aws\Sdk($this->getAwsOptions());
        }

        return $this->sdkFactory;
    }

    function getAwsClient ($service) {
        if (!isset($this->awsClients[$service])) {
            $this->awsClients[$service] = $this->getSdkFactory()->createClient($service);
        }

        return $this->awsClients[$service];
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
        catch (\Exception $e) {
            error_log("Error processing SNS notification: ".$e->getMessage());
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
            throw new \Exception("Unsupported SNS message type '$messageType'");
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
        elseif ($notificationType == 'AmazonSnsSubscriptionSucceeded') {
            error_log("SES Subscription success.");
        }
        else {
            throw new \Exception("Unsupported SES notification '$notificationType'");
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
        $this->validateNonce($_POST[$this->nonceKey], $action);

        if (in_array($action, $this->adminActions) && method_exists($this, $action)) {
            try {
                $this->$action();
            }
            catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
    }

    function validateNonce ($nonce, $action) {
        if (!wp_verify_nonce($nonce, $action) || !current_user_can($this->adminCapability)) {
            wp_die("Unauthorized");
        }
    }

    function doCSVExport () {
        $this->validateNonce($_GET[$this->nonceKey], $this->exportActionText);
        $filters = array_intersect_key($_GET, array_fill_keys(NotificationListTable::$filterKeys, ""));
        $filters['limit'] = 0;
        $records = $this->data->getNotificationList($filters);
        $csv = new \gn_CSVWriter();

        $csv->doCSVResponse("gnses-export-".time(), $records);
    }

    function test_email () {
        $formData = array_intersect_key($_POST, array_fill_keys(array("email", "subject", "message"), ""));
        if ($errors = $this->validateEmailInput($formData)) {
            $this->errors = $errors;
            return;
        }

        $this->setMailCallbacks();
        add_action("wp_mail_failed", array(&$this, "setEmailError"));

        if ($result = wp_mail($formData['email'], $formData['subject'], $formData['message'])) {
            $this->msg = "Test email sent successfully.";
        }
    }

    function validateEmailInput ($data) {
        $errors = array();
        foreach ($data as $key=>$value) {            
            if ($key=='email' && !$this->isEmail($value)) { 
                $errors[] = "Invalid email address.";
            }
            elseif (!$this->hasInput($value)) {
                $errors[] = ucfirst($key)." is required.";
            }
        }

        return $errors;
    }

    function getSMTPPassword ($key) {
        $message = "SendRawEmail";
        $hexVersion = "02";
        $hexSignature = hash_hmac("sha256", $message, $key);

        return base64_encode(hex2bin($hexVersion . $hexSignature));
    }

    function setEmailError ($error) {
        $this->errors[] = "Email failed: ".$error->get_error_message();
    }

    function wpErrorException ($error) {
        throw new \Exception($error->get_error_message());      
    }

    function update_settings () {
        $input = $_POST[$this->optionsKey];
        $newOptions = array_merge(array("suppress_bounce"=>0, "remove_tables"=>0), array_intersect_key($input, $this->getOptionDefaults()));
        if ($errors = $this->validateOptions($newOptions)) {
            $this->errors = $errors;
            return;
        }
        else {
            
            $this->setAwsOptions(array(
                "credentials"=>array(
                    "key"=>$newOptions['username'],
                    "secret"=>$newOptions['password']
                ),
                "region"=>$newOptions['ses_region']
            ));

            try {
                
                $newIdentity = $this->getSESIdentity($newOptions['from_address'], $newOptions['ses_identity']);
                $this->verifyAwsIdentity($newIdentity);
                $newOptions['_identity_verified'] = 1;

                $newOptions["_smtp_password"] = $this->getSMTPPassword($newOptions['password']);                
                $this->verifySMTP($newOptions);
                $newOptions['_smtp_ok'] = 1;

                if ($newOptions["suppress_bounce"]) {
                    $this->setNotificationHandler();
                }
                else {
                    $this->unsetNotificationHandler();
                }
            }
            catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
                return;
            }
            
            $this->setOptions($newOptions);

            $this->msg = "Settings updated.";
        }
    }

    function verifySMTP ($options) {        
        $this->setEmailOptions($options);
        $this->setMailCallbacks();
        add_action("wp_mail_failed", array(&$this, "wpErrorException"));
        try {
            wp_mail("success@simulator.amazonses.com", "Test", "Test");
        }
        catch (\Exception $e) {
            throw new \Exception("Failed sending test email: ".$e->getMessage());            
        } 
    }

    function verifyAwsIdentity ($identity) {
        $sesClient = $this->getAwsClient("Ses");
        $result = $sesClient->getIdentityVerificationAttributes(array('Identities'=>array($identity)));
        if ($result['VerificationAttributes'][$identity]['VerificationStatus'] != 'Success') {
            throw new \Exception("AWS identity '$identity' not verified.");         
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

    function getOption ($key, $default=null) {
        $options = $this->getOptions();
        $value = $options[$key];

        return strlen($value) ? $value : $default;
    }

    function setOption ($key, $value) {
        $options = $this->getOptions();
        $options[$key] = $value;
        update_option($this->optionsKey, $options);        
    }

    function setOptions ($newOptions) {
        $options = $this->getOptions();
        update_option($this->optionsKey, array_merge($options, $newOptions));
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
                "from_name"=>"",
                "ses_identity"=>"email",
                "ses_region"=>""
            );
        }

        return $this->optionDefaults;
    }

    function mainPageData ($data) {
        $data = $this->errors ? $_POST[$this->optionsKey] : $this->getOptions();
        return $data;
    }

    function setMailCallbacks () {
        add_filter("wp_mail_from", array(&$this, "mailFrom"));
        add_filter("wp_mail_from_name", array(&$this, "mailFromName"));
        add_action('phpmailer_init', array(&$this, 'setMailerConfig'));
        add_action("wp_mail_failed", array(&$this, 'logMailError'));
    }


    function mailFrom ($address) {
        return $this->getEmailOption("from_address", $address);
    }

    function mailFromName ($name) {
       return $this->getEmailOption("from_name", $name);
    }

    function logMailError ($error) {
        error_log("gn-ses: Email failed: ".$error->get_error_message());
    }   

    function setMailerConfig ($phpmailer) {
        $options = $this->getEmailOptions();

        $phpmailer->isSMTP();     
        $phpmailer->Host = $options['host'];
        $phpmailer->SMTPAuth = true; 
        $phpmailer->Port = $options['port'];
        $phpmailer->Username = $options['username'];
        $phpmailer->Password = $options['_smtp_password'];
        $phpmailer->SMTPSecure = "tls";       

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
                    error_log("gn-ses: Removing suppressed address from '$type' field: $address");
                }
                else {
                    $phpmailer->$addMethod($address, $name);
                }
            }
        }

        if (count($phpmailer->getAllRecipientAddresses()) == 0) {
            error_log("gn-ses: Warning: Message has no recipients.");
        }

    }

    function getSESIdentity ($email=null, $identityOption=null) {
        $email = is_null($email) ? $this->getOption("from_address") : $email;
        $identityOption = is_null($identityOption) ? $this->getOption("ses_identity") : $identityOption;

        return ($identityOption == "email") ? $email : substr($email, strpos($email, '@')+1);
    }

    function setNotificationHandler () {        
        $topicARN = $this->createSNSTopic();
        $this->setSESNotification("Bounce", $topicARN);
        $this->setSESNotification("Complaint", $topicARN);
    }

    function setSESNotification ($type, $topicARN) {
        $sesClient = $this->getAwsClient("Ses");
        $params = array(
            'Identity'=>$this->getSESIdentity(),
            'NotificationType'=>$type
        );

        if ($topicARN) {
            $params['SnsTopic'] = $topicARN;
        }

        $sesClient->setIdentityNotificationTopic($params);

    }

    function setSESFeedbackForwarding ($state = true) {
        $sesClient = $this->getAwsClient("Ses");
        $sesClient->setIdentityFeedbackForwardingEnabled(array(
            'ForwardingEnabled'=>$state,
            'Identity'=>$this->getSESIdentity()
        ));
    }

    function createSNSTopic () {
        $snsClient = $this->getAwsClient("Sns");
        $topic = $snsClient->createTopic(array("Name"=>"gnses-notifications"));
        $topicARN = $topic['TopicArn'];

        $snsClient->subscribe(array(
            'Endpoint'=>admin_url("admin-ajax.php?action=sns_notify"),
            'Protocol'=>(is_ssl() ? "https" : "http"),
            'TopicArn'=>$topicARN
        ));

        $this->setOption("_topic_arn", $topicARN);

        return $topicARN;
    }

    function deleteSNSTopic () {
        if ($topicARN = $this->getOption("_topic_arn")) {
            $snsClient = $this->getAwsClient("Sns");
            $snsClient->deleteTopic(array(
                'TopicArn'=>$topicARN
            ));
        }

        $this->setOption("_topic_arn", null);
    }

    function unsetNotificationHandler () {        
        $this->setSESFeedbackForwarding(true);
        $this->setSESNotification("Bounce", null);
        $this->setSESNotification("Complaint", null);
        $this->deleteSNSTopic();       
    }
    
}


