<?php

namespace gnaritas\ses;

class SesData extends \gn_PluginDB
{
    function __construct () {
        parent::__construct("gnses");
        // there should only be one of these per site.
        $this->localizeNames = false;
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
}