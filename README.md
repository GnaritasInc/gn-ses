# gn-ses
WordPress plugin for Amazon SES

## Description
This plugin sends WordPress site email through Amazon's Simple Email Service (SES) and can optionally monitor bounce and complaint notifications through Amazon's Simple Notification Service (SNS) and suppress sending email to bounced or complained addresses.

## Requirements
* PHP >= 5.5
* OpenSSL PHP extension
* Composer (for installation)
* Amazon Web Services account

## Installation
* Download or clone this repository.
* Run `composer install` in the project root directory. The Amazon SDK for PHP and other dependencies of this plugin will be installed under `vendor/`.
* Upload the contents of the project directory to a new directory under your WordPress plugins directory.
* Activate the plugin in the WordPress plugin admin page.

## Configuration
### In AWS:
#### IAM
The plugin requires an Identity and Access Management (IAM) user with API access and the following permissions:

* SES permissions:
	* SendRawEmail
	* SetIdentityNotificationTopic
	* SetIdentityFeedbackForwardingEnabled
	* GetIdentityVerificationAttributes
* SNS permissions:
	* CreateTopic
	* DeleteTopic
	* Subscribe
	* Unsubscribe

If you're unfamiliar with IAM, you can use the following recommended procedure to create a user with the necessary permissions:

1. Create an IAM policy with the required permissions:
	* From the IAM dashboard, click "Policies" in the left-hand navigation
	* Click "Create Policy", and then "Select" under "Create Your Own Policy"
	* Enter a name for the policy (e.g. "SesSendAndSetNotifications") and an optional description
	* Enter the following JSON policy definition under "Policy Document":
	
	```json
	{
	    "Version": "2012-10-17",
	    "Statement": [
	        {
	            "Effect": "Allow",
	            "Action": [
	                "ses:SendRawEmail",
	                "ses:SetIdentityNotificationTopic",
	                "ses:SetIdentityFeedbackForwardingEnabled",
	                "ses:GetIdentityVerificationAttributes"
	            ],
	            "Resource": [
	                "*"
	            ]
	        },
	        {
	            "Effect": "Allow",
	            "Action": [
	                "sns:CreateTopic",
	                "sns:DeleteTopic",
	                "sns:Subscribe",
	                "sns:Unsubscribe"
	            ],
	            "Resource": [
	                "*"
	            ]
	        }
	    ]
	}
	```
	
	* Click "Create Policy"

2. Attach the policy created in step 1 to a new group:
	* From the IAM dashboard, click "Groups" in the left-hand navigation, and then "Create New Group"
	* Enter a name for the new group (e.g. "ses-managers") and click "Next Step"
	* Find and select the policy you created in step 1 and click "Next Step"
		* **Tip**: Choose "Customer Managed" under "Filter" to more easily find your policy.
	* Click "Create Group"

3. Create a new user and assign it to the group created in step 2:
	* From the IAM dashboard, click "User" in the left-hand navigation, and then "Add User"
	* Enter a username for the new user
	* Check "Programmatic access" under "Access type"
		* **Note**: We recommend you do **not** check "AWS Management Console access"
	* Click "Next: Permissions"
	* Select "Add user to group" (selected by default) and check the group you created in step 2
	* Click "Next: Review"
	* Confirm the username, AWS access type and group membership, and click "Create user"
	* **Important**: Make a note of the new user's "Secret access key". You will need to enter it into the WordPress configuration page, along with the "Access key ID". You can download the user's credentials in a CSV file and/or display them on the page. *This is your only opportunity to view the user's secret access key.*
		* (If you forget to save the user's credentials, you can create a new access key for that user under "Security credentials" on the user admin page.)

#### SES
* Make sure you have at least one verified "identity" (i.e. email address or domain) through which to send email.
* Make sure your account is out of the [SES "sandbox".](http://docs.aws.amazon.com/ses/latest/DeveloperGuide/request-production-access.html)

### In WordPress:
* Enter the following in "SES Settings" admin page (under the "Amazon SES" admin menu):
	* **From address**: The email address to send mail from. This address or its domain should be verified in Amazon SES. (Defaults to WordPress' admin email.)
	* **From name** (optional): The name associated with the from address. (WordPress' default is "WordPress".)
	* **Host**: The AWS SMTP endpoint through which to send mail. Available SMTP endpoints are listed here: <http://docs.aws.amazon.com/ses/latest/DeveloperGuide/smtp-connect.html>. Choose the one for your SES region.
	* **Port**: The port on which to connect to the SMTP endpoint. Currently supported ports include 25, 465 and 587.
	* **Access key ID, Access key Secret**: The access key ID and secret access key for the IAM user you're using to send email.
		* **Note**: SMTP credentials created using the SES control panel's "Create My SMTP Credentials" will not work. If you're using an IAM user that was created this way, you should create a new access key for them in the "Security credentials" tab on their IAM configuration page and use those credentials.
		* **Security note**: If your WordPress site doesn't use https, the secret access key will be vulnerable to interception by third parties when submitting or viewing the SES settings page. It is *not* exposed when sending email or accessing the AWS API.
	* **SES Region**: The identifier for your SES account's region. Supported regions are listed here: <http://docs.aws.amazon.com/general/latest/gr/rande.html#ses_region>. Use the value in the "Region" column (e.g. "us-east-1").
		* **Note**: If you are using the plugin to handle bounces and complaints, your Simple Notification Service (SNS) region must be the same as your SES region.
	* **SES Identity Type**: Choose "Email" or "Domain".
	* Choose whether or not you'd like the plugin to suppress email to bounced or complained recipients

* On submission, the plugin will verify your Amazon SES identity and send a test email to the SES simulator. If successful, WordPress will send email through Amazon SES.

* If you opted have the plugin suppress email to bounced or complained addresses, bounce and complaint notifications for your SES identity will be handled by the plugin.

* If email sending fails unexpectedly, the plugin will stop attempting to send email through SES and revert back to WordPress' default email handling. This will also stop suppression of email to bounced or complained addresses.

* To resume sending through Amazon SES, verify your settings on the settings page and click "Save Changes". The plugin will attempt to verify your SES identity and send a test email, and if successful, resume sending through SES.

* The plugin displays notifications indicating its current email and bounce handling state on its admin pages and on the WordPress admin dashboard.

## Sending Test Email
You can send a test email using the current configuration from the WordPress admin panel under "Amazon SES > Test Email".

## SNS Notifications and Bounce/Complaint Handling
If you opt to have the plugin suppress email to bounced or complained addresses, bounce and complaint notifications from Amazon Simple Notification Service (SNS) will be recorded in the WordPress database. You can view the notifications and export them in CSV format in the WordPress admin panel under "Amazon SES > SNS Notifications".

While the plugin is active and email suppression is in effect, email will be suppressed to addresses associated with any "Complaint" notification or a "Permanent" bounce notification. Bounces of type "Transient" are recorded but will not result in suppression of future email.
