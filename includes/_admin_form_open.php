<?php $plugin->displayMessages();?>
<form method="POST">
<?php wp_nonce_field($action, $plugin->nonceKey); ?>
<input type="hidden" name="<?php echo $plugin->adminActionKey; ?>" value="<?php echo $action; ?>"/>