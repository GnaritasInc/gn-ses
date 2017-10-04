<?php include("_admin_page_open.php"); ?>
<?php include("_admin_form_open.php"); ?>

<?php $context = $_POST; ?>

<table class="form-table">
<tbody>
    <tr>
        <th scope="row"><label for="gnses_email">To:</label></th>
        <td><input type="text" class="regular-text" id="gnses_email" name="email" value="{{email}}" /></td>
    </tr>
    <tr>
        <th scope="row"><label for="gnses_subject">Subject:</label></th>
        <td><input type="text" class="regular-text" id="gnses_subject" name="subject" value="{{subject}}" /></td>
    </tr>
    <tr>
        <th scope="row"><label for="gnses_message">Message:</label></th>
        <td><textarea id="gnses_message" name="message" cols="80" rows="10">{{message}}</textarea></td>
    </tr>
</tbody>
</table>

<p class="submit">
    <input type="submit" value="Send Test Email" class="button-primary" />
</p>
<?php include("_admin_form_close.php"); ?>
<?php include("_admin_page_close.php"); ?>