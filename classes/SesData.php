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
}