<?php
class Locamore_Db_Table extends Zend_Db_Table {
  
  public function getPrimary() 
  {
    return $this->_primary;
  }
  
}