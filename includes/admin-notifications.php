<?php include("_admin_page_open.php");  

$listTable = new \gnaritas\ses\NotificationListTable($this->plugin); 
$listTable->prepare_items();
?>

<form method="get">
<?php $listTable->display(); ?>
</form>

<?php include("_admin_page_close.php"); ?>