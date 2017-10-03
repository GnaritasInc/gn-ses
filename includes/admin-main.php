<?php include("_admin_page_open.php"); ?>
<?php include("_admin_form_open.php"); ?>
<h2>Email Settings</h2>
<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">
                <label for="<?php echo "{$prefix}-host"?>">Host</label>
            </th>
            <td>
                <input name="<?php echo "{$optionsKey}[host]"; ?>" id="<?php echo "{$prefix}-host"?>" value="{{host}}" class="regular-text" type="text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo "{$prefix}-port"?>">Port</label>
            </th>
            <td>
                <input name="<?php echo "{$optionsKey}[port]"; ?>" id="<?php echo "{$prefix}-port"?>" value="{{port}}" class="small-text" type="number" min="1" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo "{$prefix}-username"?>">Username</label></th>
                <td>
                    <input name="<?php echo "{$optionsKey}[username]"; ?>" id="<?php echo "{$prefix}-username"?>" value="{{username}}" class="regular-text" type="text"/>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo "{$prefix}-password"?>">Password</label>
            </th>
            <td>
                <input name="<?php echo "{$optionsKey}[password]"; ?>" id="<?php echo "{$prefix}-password"?>" value="{{password}}" class="regular-text" type="text"/>
            </td>
        </tr>
    </tbody>
</table>
<h2>Bounce Handling</h2>
<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">&nbsp;</th>
            <td> 
                <label><input type="checkbox" name="<?php echo "{$optionsKey}[suppress_bounce]"; ?>" value="1" <?php checked($context['suppress_bounce'], 1); ?>/> Suppress email to bounced/compained recipients?</label>
            </td>
        </tr>
    </tbody>
</table>
<h2>Deactivation</h2>
<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">&nbsp;</th>
            <td>
                <label><input type="checkbox" name="<?php echo "{$optionsKey}[remove_tables]"; ?>" value="1" <?php checked($context['remove_tables'], 1); ?>/> Remove notification logs on deactivation?</label>
            </td>
        </tr>
    </tbody>
</table>                
<p class="submit">
    <input type="submit" value="Save Changes" class="button-primary" />
</p>
<?php include("_admin_form_close.php"); ?>
<?php include("_admin_page_close.php"); ?>