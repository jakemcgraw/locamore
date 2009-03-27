<?php
class Model_Table_Job extends Locamore_Db_Table {
  protected $_name = 'job';
  protected $_primary = array('job_id');
  protected $_dependantTables = array('User');
}
