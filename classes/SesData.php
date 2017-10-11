<?php

namespace gnaritas\ses;

class SesData extends \gn_PluginDB
{
    function __construct () {
        parent::__construct("gnses");
        // there should only be one of these per site.
        $this->localizeNames = false;
        $this->tableDefinition = array(
            "notification"=>array(
                "columns"=>array('id', 'email', 'notification_type', 'notification_date', 'feedback_id', 'bounce_type', 'bounce_subtype', 'complaint_feedback_type', 'resend')
            )
        );
    }

    function initTableDefinitions () {
        include(dirname(dirname(__FILE__)) . "/includes/table-defs.php");
    }

    function dropTables () {
        foreach (array_keys($this->tableDefinitions) as $key) {
            $tableName = $this->tableName($key);
            $sql = "drop table if exists $tableName";
            $this->db->query($sql);
        }
    }

    function getCreateTableSQL ($name) {
        $sql = "CREATE TABLE ".$this->tableName($name);
        $sql .= $this->tableDefinitions[$name];

        return $this->replaceTableRefs($sql);
    }

    function insureTables () {
        $statements = array();
        foreach (array_keys($this->tableDefinitions) as $key) {
            $statements[] = $this->getCreateTableSQL($key);
        }

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta($statements);
    }    

    function logBounce ($bounce) {
        foreach ($bounce->bouncedRecipients as $recipient) {
            $bounceData = array(
                "email"=>$recipient->emailAddress,
                "notification_type"=>"Bounce",
                "notification_date"=>date("Y-m-d H:i:s"),
                "feedback_id"=>$bounce->feedbackId,
                "bounce_type"=>$bounce->bounceType,
                "bounce_subtype"=>$bounce->bounceSubType,
                "resend"=>($bounce->bounceType == "Permanent" ? 0 : 1)
            );
            $this->insertNotification($bounceData);
        }
    }

    function logComplaint ($complaint) {
        foreach($complaint->complainedRecipients as $recipient) {
            $complaintData = array(
                "email"=>$recipient->emailAddress,
                "notification_type"=>"Complaint",
                "notification_date"=>date("Y-m-d H:i:s"),
                "feedback_id"=>$complaint->feedbackId,
                "complaint_feedback_type"=>$complaint->complaintFeedbackType,
                "resend"=>0
            );

            $this->insertNotification($complaintData);
        }
    }

    function insertNotification ($data) {
        $this->doInsert("notification", $data);
    }

    function isSuppressed ($address) {
        $sql = "select count(*) from ".$this->tableName("notification")." where email=%s and resend=0";
        return $this->db->get_var($this->db->prepare($sql, $address));
    }

    function getNotificationCount ($params=array()) {
        $sql = "select count(*) from ".$this->tableName("notification");
        $sql .= $this->getNotificationFilterSQL($params);
        return $this->db->get_var($sql);
    }

    function getNotificationList ($args) {
        $defaults = array(
            "offset"=>0,
            "limit"=>20,
            "order_by"=>"notification_date",
            "order"=>"desc"
        );

        $params = array_merge($defaults, $args);

        $sql = "select * from ".$this->tableName("notification");
        $sql .= $this->getNotificationFilterSQL($params);
        $sql .= $this->getOrderBy("notification", $params['order_by'], $params['order']);
        if (intval($params['limit'])) {
            $sql .= $this->db->prepare(" limit %d, %d", $params['offset'], $params['limit']);
        }            

        return $this->db->get_results($sql, ARRAY_A);

    }

    function getOrderBy ($table, $column, $order="asc") {
        $tableCols = $this->tableDefinition[$table]["columns"];
        if (in_array($column, $tableCols) && in_array($order, array("asc", "desc"))) {
            return sprintf(" order by %s %s", $column, $order);
        }
        else {
            return "";
        }
    }

    function getNotificationFilterSQL ($params) {
        $conditions = array();
        $filterKeys = array("date_start", "notification_type", "filter_email", "bounce_type");
        foreach($filterKeys as $key) {
            $val = trim($params[$key]);
            if (!strlen($val)) {
                continue;
            }
            if ($key == "date_start" && $endDate = trim($params['date_end'])) {
                $endDate .= " 23:59:59";
                $conditions[] = $this->db->prepare("notification_date between %s and %s", $val, $endDate);
            }
            elseif ($key == "filter_email") {
                $filterValue = '%'.$this->db->esc_like($val).'%';
                $conditions[] = $this->db->prepare("email like %s", $filterValue);
            }
            else {
                $conditions[] = $this->db->prepare("$key = %s", $val);
            }
        }

        return count($conditions) ? " where ".implode(" and ", $conditions) : "";
    }
}
