<?php

namespace gnaritas\ses;

class NotificationListTable extends \WP_List_Table
{
    public $plugin;

    
    function __construct (&$plugin) {
        parent::__construct(array(
            "singular"=>"Notification",
            "plural"=>"Notifications",
            "ajax"=>false
        ));

        $this->plugin = $plugin;
    }

    function get_columns () {
        return array(
            "notification_type"=>"Notification Type",
            "notification_date"=>"Date",
            "bounce_type"=>"Bounce Type",
            "email"=>"Email",
            "resend"=>"Send email to this address?"
        );
    }

    function get_sortable_columns () {
        return array(
            "notification_type"=>array("notification_type", false),
            "notification_date"=>array("notifcation_date", false),
            "bounce_type"=>array("bounce_type", false),
            "email"=>array("email", false)
        );
    }

    function column_default ($item, $column_name) {
        return $item[$column_name];
    }

    function column_resend ($item) {
        return intval($item['resend']) ? "Yes" : "No";
    }

    function prepare_items () {
        $data = $this->plugin->data;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

         $filters = array_intersect_key($_GET, array_fill_keys(array("date_start", "date_end", "notification_type", "filter_email", "bounce_type"), ""));

        $per_page = 5;
        $current_page = $this->get_pagenum();
        $total_items = $data->getNotificationCount($filters);

        $orderCol = isset($sortable[$_GET['orderby']]) ? $sortable[$_GET['orderby']][0] : "notification_date";
        $order = in_array($_GET['order'], array('asc', 'desc')) ? $_GET['order'] : "desc";

        $args = array(
            "limit"=>$per_page,
            "offset"=>intval($_GET['paged']),
            "order_by"=>$orderCol,
            "order"=>$order
        );       

        $this->items = $data->getNotificationList(array_merge($args, $filters));

        $this->set_pagination_args(array(
            "total_items"=>$total_items,
            "per_page"=>$per_page,
            "total_pages"=>ceil($total_items/$per_page)
        ));
    }

    
    // overriding to get rid of text box
    function display_tablenav ($which) {
        parent::display_tablenav("bottom");
    }
}
