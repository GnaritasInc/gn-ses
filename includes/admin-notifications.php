<?php include("_admin_page_open.php");  

$listTable = new \gnaritas\ses\NotificationListTable($this->plugin); 
$listTable->prepare_items();

?>

<form method="get">
<?php wp_nonce_field($plugin->exportActionText, $plugin->nonceKey, false); ?>
<input type="hidden" name="page" value="{{page}}"/>
<h2>Filters</h2>
<table class="form-table">
    <tbody>
        <tr>
            <th scope="row"><label>Date range:</label></th>
            <td><input type="date" name="date_start" value="{{date_start}}"/> to <input type="date" name="date_end" value="{{date_end}}"/></td>
        </tr>
        <tr>
            <th scope="row"><label for="notification_type">Notification type:</label></th>
            <td>
                <select id="notification_type" name="notification_type">
                    <option value="">All</option>
                    <option <?php selected($_GET['notification_type'], "Bounce"); ?>>Bounce</option>
                    <option <?php selected($_GET['notification_type'], "Complaint"); ?>>Complaint</option>
                </select>
            </td>
        </tr>
         <tr>
            <th scope="row"><label for="bounce_type">Bounce type:</label></th>
            <td>
                <select id="bounce_type" name="bounce_type">
                    <option value="">All</option>
                    <option <?php selected($_GET['bounce_type'], "Permanent"); ?>>Permanent</option>
                    <option <?php selected($_GET['bounce_type'], "Transient"); ?>>Transient</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="filter_email">Email contains:</label></th>
            <td><input type="text" name="filter_email" id="filter_email" value="{{filter_email}}"/></td>
        </tr>
    </tbody>
</table>
<p><input type="submit" class="button button-secondary" value="Apply Filters"/></p>
<p><input type="submit" class="button button-primary" name="<?php echo $plugin->adminActionKey; ?>" value="<?php echo $plugin->exportActionText; ?>"/></p>
<?php $listTable->display(); ?>
</form>

<?php include("_admin_page_close.php"); ?>