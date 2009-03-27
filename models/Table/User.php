<?php
class Model_Table_User extends Locamore_Db_Table {
  protected $_name = 'user';
  protected $_primary = array('user_id');
  protected $_referenceMap = array(
    'Job' => array(
      'columns'         => 'fk_job_id'
      , 'refTableClass' => 'Job'
      , 'refColumns'    => 'job_id'
    )
  );
}
