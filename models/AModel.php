<?php

abstract class Model_AModel {  

  /**
   * @var Zend_Loader_PluginLoader
   */
  protected $_loader;
  
  /**
   * @var array Table instances
   */
  protected $_tables = array();
  
  /**
   * @var string Primary table for operations
   */
  protected $_primaryTable = 'user';
  
  /**
   * @var array Columns that may not be specified in save operations
   */
  protected $_protectedColumns = array();

  /**
   * Get plugin loader
   * 
   * @return Zend_Loader_PluginLoader
   */
  public function getPluginLoader()
  {
    if (null === $this->_loader) {
      $this->_loader = new Zend_Loader_PluginLoader();
      $this->_loader->addPrefixPath('Model_Table', dirname(__FILE__) . '/Table/');
    }
    return $this->_loader;
  }

  /**
   * Get table class
   * 
   * @param  string $name 
   * @return Zend_Db_Table_Abstract
   */
  public function getTable($name = null)
  {
    $name = ucfirst((null === $name) ? $this->_primaryTable : $name);
    if (!array_key_exists($name, $this->_tables)) {
      $class = $this->getPluginLoader()->load($name);
      $this->_tables[$name] = new $class;
    }
    return $this->_tables[$name];
  }

  /**
   * Insert or update a row
   * 
   * @param  array $info New or updated row data
   * @param  string|null Table name to use (defaults to primaryTable)
   * @return int Row ID of saved row
   */
  public function save(array $info, $tableName = null)
  {
    $tableName = (null === $tableName) ? $this->_primaryTable : $tableName;
    $table = $this->getTable($tableName);
    $row = null;
    
    // Get PKs from $info
    $pks = $this->_filterPrimary($info, $tableName);
    
    if (null !== $pks) {
      // Remove PKs from $info
      $info = array_diff_assoc($info, $pks);
      $matches = call_user_func_array(array($table, 'find'), $pks);
      if (0 < count($matches)) {
       $row = $matches->current();
      }
    }
    
    // Unable to find row to update
    if (null === $row) {
      $row = $table->createRow();
      $row->created = date('Y-m-d H:i:s');
    }
    
    // Remove protected columns from $columns
    $columns = array_diff_assoc($table->info('cols'), $this->_protectedColumns);
    foreach($columns as $column) {
      if (array_key_exists($column, $info)) {
        // Set columns on $row
        $row->$column = $info[$column];
      }
    }
    
    // Save row and return
    return $row->save();
  }

  /**
   * Determine whether to update or insert data based on a comparison between data and PKs
   * 
   * @param $info array Data to insert/update
   * @param $tableName string 
   * @return mixed array representing PKs in $info, null if missing a PK or null for PK
   */
  protected function _filterPrimary(array $info, $tableName) {
    
    $primaryKeys = $this->getTable($tableName)->getPrimary();
    
    // Get all keys in $info that are values in $primaryKeys
    $infoPrimaryKeys = array_intersect_key(
      $info, array_fill_keys($primaryKeys, null)
    );
    
    // All PKs available in $info
    if (count($primaryKeys) === count($infoPrimaryKeys)) {
      
      // Check for nulls in $info PKs
      if (0 === count(array_intersect($infoPrimaryKeys, array_fill(0, count($infoPrimaryKeys), null)))) {
        
        // All PKs available in $info and not null
        // Reorder to match definition
        $result = array();
        foreach($infoPrimaryKeys as $idx => $value) {
          $result[array_search($idx, $primaryKeys)] = $value;
        }
        return $result;
      }
      
    }
    
    // Either missing a PK or a PK value is null
    return null;
  }
}
