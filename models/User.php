<?php

require_once dirname(__FILE__).'/AModel.php';

class Model_User extends Model_AModel
{
  protected $_primaryTable = 'user';
  
  public function save(array $data, $table = null)
  {
    if (null === $table || $table == $this->_primaryTable) {
      // Set updated
      if (!isset($data['updated'])){
        $data['updated'] = date('Y-m-d H:i:s');
      }
      // Modify g_region, g_country_code
      if (isset($data['g_country_code'])) {
        $data['g_country_code'] = strtolower($data['g_country_code']);
        if ($data['g_country_code'] == 'us') {
          if (isset($data['g_region'])) {
            $data['g_region'] = strtolower($data['g_region']);
          }
        }
      }
    }
    
    return parent::save($data, $table);
  }
  
  public function batchSave(array $batch, $job_id) 
  {
    $message = array();
    $success = 0;
    $failure = 0;
    $total = count($batch);
    foreach($batch as $data) {
      $data = (array) $data;
      $data['fk_job_id'] = $job_id;
      $data['city']   = ('' === $data['city']) ? null : $data['city'];
      $data['geo']    = (null === $data['city']) ? 0 : null;
      try {
        $user_id = $this->save($data);
        if ($user_id != $data['user_id']) {
          $failure++;
          $message[] = array(
            'error'   => sprintf('user_id mismatch (%d)', $user_id)
            , 'data'  => $data
          );
        } else {
          $success++;
        }
      } catch (Zend_Exception $ze) {
        $failure++;
        $message[] = array(
          'error'   => $ze->getMessage()
          , 'data'  => $data
        );
      }
    }
    return array(
      'total' => $total
      , 'success' => $success
      , 'failure' => $failure
      , 'message' => $message
    );
  }
  
  public function getGeoBatch($limit = 10) 
  {
    $table = $this->getTable('user');
    return $table->fetchAll($table->select()
      ->from('user', array('user_id', 'city'))
      ->where('geo IS NULL')
      ->limit($limit)
    )->toArray();
  }
}