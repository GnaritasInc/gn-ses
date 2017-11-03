<?php
if(!class_exists('gn_BasePluginDB')):

class gn_BasePluginDB {
	var $db;  
	var $dbVersion;
	var $tableDefinitions;
	var $debug = true;
	var $cache = array();
	var $do_cache = true;
	var $localizeNames = true;

	function __construct($pluginprefix) {

	  $this->pluginprefix =$pluginprefix;
	  $this->init();


	}

	function init() {
		global $wpdb;
		$this->dbVersion = "1.0";
		$this->db = $wpdb;
		$this->initTableDefinitions();
	}

	function showdebug ($str) {
		if ($this->debug) {
			error_log("gnDB log:".$str);
		}
	}

	function showdebugObject($object) {
		ob_start();
		print_r($object);
		$str=ob_get_clean();
		$this->showdebug($str);
	}

	function get_cached_results($sql, $output=OBJECT) {

		$key = $sql.":".$output;

		if (array_key_exists ($key, $this->cache)) {
			$this->showdebug("Cached result:".$sql);

			$results = $this->cache[$key];
		}
		else {
			//$this->showdebug("$key not in cache");
			//$this->showdebugObject($this->cache);
			$this->showdebug("Queried result:".$sql);
			$results = $this->db->get_results($sql, $output);
			$this->cache[$key] = $results;
		}
		return ($results);
	}


	function get_results($sql, $output=OBJECT) {

		if ($this->do_cache && preg_match('/^select /i',$sql)) {
			return ($this->get_cached_results($sql,$output));
		}
		else {
			return $this->db->get_results($sql, $output);
		}
	}

	function dbExecute($sql) {
	  	$this->showdebug("dbExecute:".$sql);
		return($this->db->query($sql));
	}

	function dbSafeExecute ($sql) {
		$result = $this->dbExecute($sql);

		if($result === false) {			
			throw new Exception("Database error: ".$this->db->last_error);
		}
		else return $result;
	}

	function tablePrefix () {
	  return(($this->localizeNames?$this->db->prefix:"") .$this->pluginprefix."_");
	}

	function var_to_string($object) {
		ob_start();
		var_dump($object);
		$str=ob_get_contents();
		ob_end_clean();
		return($str);
	}

	function replaceReferences($matches) {
	  return ("references ".$this->tableName($matches[1]));
	}

	function replaceKey($matches) {
	  return ("KEY ".$this->tableName($matches[1])." ");

	}

	function replaceConstraint($matches) {
	  return ("CONSTRAINT ".$this->tableName($matches[1])." ");

	}

	function createTable ($name) {

		$this->showdebug("Creating $name table");

		$sql ="CREATE TABLE ".$this->tableName($name)." ";

		$sql.=$this->tableDefinitions[$name];

		$sql = $this->replaceTableRefs($sql);

		error_log("create table sql: $sql");

		$this->db->show_errors();

		$this->db->query($sql);

	}


	function tablesInstalled() {
		reset($this->tableDefinitions); // make sure array pointer is at first element
		$firstTable = key($this->tableDefinitions);
		$firstTableName = $this->tableName($firstTable);
		return ($this->db->get_var("show tables like '$firstTableName'") == $firstTableName);

	}

	function tableExists ($key) {
		$tableName = $this->tableName($key);
		return ($this->db->get_var("show tables like '$tableName'"));
	}

	function insureTables () {}

	function upgradeTables() {}

	function initTableDefinitions() {}

	function quoteString($str) {
		if (is_array($str)) {
			$str=implode(",",$str);
		}

		return $this->db->prepare('%s', stripslashes($str));

	}

	function quoteInteger ($str){

		return $this->db->prepare('%d', $str);
	}

	function quoteNumericList ($str) {
		$values = preg_split('/,\s*/', $str);
		$format = implode(',', array_fill(0, count($values), '%d'));
		return $this->db->prepare($format, $values);
	}

	function filterArray($source,$filter) {
		$outArray = array();

		foreach($source as $key=>$value){
			if(in_array($key, $filter)){
				if (is_array($value) && count($value)==1) {
					$value=$value[0];
				}
				$outArray[$key] = $value;
			}
		}
		return($outArray);
	}

	function quoteData ($pageData, $nullEmptyString=false) {


		foreach($pageData as $key=>$value){
				if($nullEmptyString) {
					$pageData[$key] = $pageData[$key]==='' ? 'null' : $this->quoteString($value);
				}
				else $pageData[$key] = $this->quoteString($value);

		}

		return $pageData;

	}

	function loadValues($fieldArray, $valueArray) {

		$sql='';
		$mappedArray=$this->filterArray($valueArray, $fieldArray);	

		$quotedArray = $this->quoteData ($mappedArray, true);

		foreach ($quotedArray as $key=>$value) {	
			$sql.=$sql?',':'';
			$sql.=" $key=$value ";	
		}
		return($sql);
	}


	function valueList($fieldArray, $valueArray) {
		$sql='';
		$mappedArray=$this->filterArray($valueArray, $fieldArray);
		$quotedArray = $this->quoteData ($mappedArray);


		$outputArray= array();
		foreach ($quotedArray as $key=>$value) {
			if ($key!='id') {
				$outputArray[] =$value;
			}
		}
		return(" (".implode("," ,$outputArray).") ");
	}

	function columnList($fieldArray, $valueArray) {
		$sql='';
		$mappedArray=$this->filterArray($valueArray, $fieldArray);
		$quotedArray = $this->quoteData ($mappedArray);

		$outputArray= array();
		foreach ($quotedArray as $key=>$value) {
			if ($key!='id') {
				$outputArray[] =$key;
			}
		}
		return(" (".implode("," ,$outputArray).") ");
	}

	function getRecord($sql) {
		
	  	$results=$this->get_results ($sql);

	  	if (count($results)>0) {
	  		return ($results[0]);
	  	}

	  	else return null;
	}

	function getRecordArray($sql) {
		
	  	$results=$this->get_results ($sql,ARRAY_A);

	  	if (count($results)>0) {
	  		return ($results[0]);
	  	}

	  	else return null;
	}

	function getInsertID () {
		return $this->db->insert_id;
	}

	function quoteIdentifier ($str) {
		return '`'.str_replace('`', '', $str).'`';
	}

	function getDBID ($table, $lookupCol, $lookupVal, $idCol='id') {
		$sql = $this->db->prepare("select ". $this->quoteIdentifier($idCol) ." from ". $this->quoteIdentifier($table) ." where ". $this->quoteIdentifier($lookupCol) ."=%s", $lookupVal);

		return $this->db->get_var($sql);

	}

	// Data definition and operations


	function getTableDefinition ($name) {
		return $this->tableDefinition[$name];
	}

	function tableDefExists ($name) {
		return array_key_exists($name, $this->tableDefinition) ? true : false;
	}

	function tableName($internalName) {
		return(($this->localizeNames?$this->db->prefix:"")  .$this->pluginprefix."_". $internalName);

	}

	function replaceTableRefs ($str) {

		return preg_replace('/#([^#]+?)#/', $this->prefixTableName("$1"), $str);
	}

	function prefixTableName ($internalName) {
		return($this->tablePrefix(). $internalName);
	}

	function contextualizeColumns($table, $columns) {
		$dataColumns= array();

		foreach ($columns as $col) {
			if ((strpos($col,".")===false) && (strpos($col,"'")===false) && (strpos($col,"(")===false) ) {
				$dataColumns[] = "$table.$col";
			}
			else {
			$dataColumns[] = $col;

			}
		}
		return ($dataColumns);
	}

	function dataColumns($name, $contextualize=true) {
		$dataColumns= array();

		if ($tableDefinition = $this->getTableDefinition($name)) {
			$table=$this->tableName($name);
			$cols= array_key_exists("datacolumns", $tableDefinition)?$tableDefinition["datacolumns"]:array('*');

			if ($contextualize)
				$dataColumns= $this->contextualizeColumns($table, $cols);
			else
				$dataColumns=  $cols;

			return ($dataColumns);

		}
		else {
			$this->showdebug("ERROR: Definition not found for $name");
			return null;
		}
	}

	function envReplacements ($sql, $atts) {

		$contextKey = $atts["context_key"] ? $atts["context_key"] : "id";
		$sql = preg_replace('/#current_id#/i', intval($_GET[$contextKey]), $sql);

		if (strpos($sql, '#current_user_id#') !== false) {
			global $user_ID;
			get_currentuserinfo();

			$sql = preg_replace('/#current_user_id#/i', intval($user_ID), $sql);
		}

		return $sql;

	}

	function tableColumns($name) {
		$tableDefinition = $this->getTableDefinition($name);
		return ($tableDefinition["columns"]);
	}


	// Data Operations


	function updateEdit ($name, $data, $noDefaults = false) {

		$sql = $this->getUpdateEditSQL($name, $data, $noDefaults);
		return($this->dbExecute($sql));
	}


	function mergeData($novel, $base) {
		if (! is_array($novel)) {
			$novel=get_object_vars($novel);
		}

		return array_merge($base, $novel);
	}

	function clearEmptyProps($data) {

		foreach ($data as $key=>$value) {
			if (!$value) {
				unset ($data[$key]);
			}
		}

		return ($data);
	}

	function genericAssignReplace($name, $key, $base_id, $elementArray) {

		$this->showdebug("*** genericAssignReplace");

		if ($key && $base_id) {

			$tableName = $this->tableName($name);

			$sql = $this->db->prepare("delete from $tableName where ".$this->quoteIdentifier($key)."=%s", $base_id);

			$this->showdebug($sql);
			$this->dbSafeExecute($sql);

			foreach ($elementArray as $element) {

				$element = array_filter($element, 'strlen');

				$tableColumns = $this->tableColumns($name);
				$insertColumns = implode(',', array_intersect(array_keys($element), $tableColumns));
				$valueFormat = implode(',', array_fill(0, count($element), '%s'));

				$sql = $this->db->prepare("insert into $tableName ($insertColumns) values ($valueFormat)", array_values($element));
				$this->showdebug($sql);
				$this->dbSafeExecute($sql);

			}

		}
		else {

			$this->showdebug("*** genericAssignReplace Error: $key : $base_id");

		}


	}

	function doMultipleInsertUpdate($name, $elementArray, $baseData) {

		$return = true ;



		foreach ($elementArray as $element) {

			if ($element["id"]) {
				$element = $this->clearEmptyProps($element);

				unset ($baseData["id"]);

				$insertData=$this->mergeData($baseData, $element);
			}
			else {
				$insertData=$this->mergeData($element, $baseData);

			}

			$singleReturn = $this->updateEdit ($name, $insertData);

			$return = ($return || $singleReturn);
		}

		return ($return);

	}



	function getIdSQL($name, $atts=array()) {
		$this->showdebug("Checking List SQL for: $name");

		if ($this->listSelectTableName($name)) {
			$sql="";

			$sql.="select ".$this->tableName($name).".id" ;
			$sql.="  from ".$this->listSelectTableName($name);

			$sql.=$this->filterList($name);
			$sql.=$this->contextFilter($name, $atts);

			$sql=$this->securityConstrainSQL($name,"list",$sql, $atts);

			return($sql);
		}
	}


    function getGroupBy($name) {
    	if (($def=$this->getTableDefinition($name)) && $def["groupby"]) {
			return($def["groupby"]);

		}

    }

	function getCountSQL($name, $atts=array()) {
		$this->showdebug("Checking Count SQL for: $name");

		$sql="";

		$sql.="select count(*)";
		$sql.="  from ".$this->listSelectTableName($name);
		$sql.=$this->filterList($name);
		$sql.=$this->contextFilter($name, $atts);

		$sql=$this->securityConstrainSQL($name,"count",$sql, $atts);

		return($sql);
	}


	function checkFlags($name, $valueArray) {
		$tableDefinition = $this->getTableDefinition($name);
		if ($cols = $tableDefinition["required_flag_fields"]) {
			foreach ($cols as $col) {
				if (! array_key_exists ($col, $valueArray)) {
					$valueArray[$col] = 0;
				}
			}
		}
		else if ($defaults = $tableDefinition["defaults"]) {
			foreach($defaults as $key=>$value) {
				if(!array_key_exists($key, $valueArray) || !strlen(trim($valueArray[$key]))) {
					$valueArray[$key] = $value;
				}
			}
		}

		return ($valueArray);
	}

	function getUpdateEditSQL($name, $valueArray, $noDefaults=false) {

		$sql='';

		if (! $noDefaults)
			$valueArray= $this->checkFlags($name, $valueArray);



		$valueArray = apply_filters("gn_db_update_edit_value_defaults_filter", $valueArray);

		if ($this->tableDefExists($name)) {
			if ($valueArray['id'] && $this->fetchObject($name, $valueArray['id'])) {

				$sql="update ".$this->tableName($name)." set ";
				$sql.= $this->loadValues($this->tableColumns($name),array_diff_key($valueArray, array("id"=>"")));

				// $sql.=", id=". $valueArray['id'];
				$sql.= " where id = ". $this->quoteString($valueArray['id']);

				$this->showdebug("Table cols:".implode(", ",$this->tableColumns($name)));
				$this->showdebug("Update SQL:".$sql);
			}
			else {


				$sql="insert into ".$this->tableName($name). ' set ';
				$sql.= $this->loadValues($this->tableColumns($name),$valueArray);


				$this->showdebug("Insert SQL:".$sql);

			}
		}
		else {
		  error_log("***Error*** Definition not found for:".$name);

		}

			return ($sql);
	}


	function getRecordSQL($id, $name, $contextualize= true) {



		if (($table=$this->listSelectTableName($name)) && $id) {

			$columns = implode($this->dataColumns($name, $contextualize),",");
			$idColumn="id";

			$singleTable = $this->tableName($name);

			$pos = strpos($table,"dataTable");

			if ($pos !== false && $pos>=0) {
				$idColumn="dataTable.id";
			}

			$sql="select $columns from ".$table;
			$sql.= " where $singleTable.$idColumn = %d";


			return $this->db->prepare($sql, $id);
		}
		else {

			$this->showdebug("Can't get record for:$name  with id $id");
		}
	}


	function doInsert ($tableKey, $data) {
		$sql = $this->getUpdateEditSQL($tableKey, $data);
		$this->dbSafeExecute($sql);
		return $this->db->insert_id;

	}


	function normalizeSpace ($str) {
		$str = preg_replace('/\s+/', ' ', $str);

		return trim($str);
	}

	function securityConstrainSQL($name, $type, $sql, $atts) {
		// to be overriden by sub classes, if necessary
		return ($sql);
	}

	function fetchObject($name, $id, $output=ARRAY_A) {
		$sql = $this->getRecordSQL ($id, $name);
		$result =  $sql ? $this->get_results($sql, $output) : array();

		return count($result) ? $result[0] : null;
	}


}
endif;
