<?php

require_once dirname(__FILE__).'/AModel.php';

class Model_Job extends Model_AModel {

  protected $_primaryTable = 'job';
  
  public function save(array $data, $table = null) 
  {
    if (null === $table || $table == $this->_primaryTable) {
      // Set total_left
      if (!isset($data['total_left'])){
        $data['total_left'] = $data['total'] - ($data['start'] + $data['found']);
      }
    }
    
    return parent::save($data, $table);
  }
  
  public function fetchLastJob()
  {
    $table = $this->getTable('job');
    return $table->fetchRow($table->select()
      ->where('success = ?', 1)
      ->order('job_id DESC')
      ->limit(1)
    );
  }
  
  public function runNextJob() 
  {
    $last = $this->fetchLastJob();
    
    // Jobs left over from last query
    if ($last && $last['total_left'] > 0) {
      $query = $last['query'];
      $start = $last['found'] + $last['start'];
    
    // Start a new job
    } else {
      if (!$last) {
        $query = 'users/keywords/a';
      } else {
        $letter = self::getNextLetter(substr($last['query'],-1));
        if (!$letter) {
          // No new jobs available
          return false;
        }
        $query = 'users/keywords/'.$letter;
      }
      $start = 0;
    }
    return $this->runJob($query, $start);
  }
  
  public function runJob($query, $start=0, $limit=50) 
  {
    
    require_once dirname(__FILE__).'/Etsy.php';

    $etsy = new Model_Etsy();
    $etsy->setResource($query);
    $etsy->setParams('offset', $start);
    $etsy->setParams('limit', $limit);
    $data = array(
      'query' => $query, 'start' => $start
    );
    $timems = self::getMicrotime();
    $result = $etsy->request();
    $data['time_ms'] = (int) floor((self::getMicrotime() - $timems) * 1000);
    $data['success'] = ($result !== null) ? 1 : 0;
    // Success
    if ($data['success']) {
      $data += array(
        'requested' => $result->params->limit
        , 'found'   => count($result->results)
        , 'total'   => $result->count
      );
    // Failure
    } else {
      $params = $etsy->getParams();
      $data += array(
        'requested' => $result->params->limit
        , 'found'   => 0
        , 'total'   => 0
        , 'code'    => $response->getStatus()
        , 'message' => $response->getMessage()
      );
    }
    
    $data['job_id'] = $this->save($data);
    $data['result'] = $result;
    
    return $data;
  }
  
  protected static function getNextLetter($letter=null) 
  {
    if ($letter === null) {
      return false;
    }
    if (!Zend_Validate::is($letter, 'Alpha')) {
      return false;
    }
    $letter = strtolower($letter[0]);
    if ($letter == 'z') {
      return false;
    }
    return chr(ord($letter)+1);
  }
  
  protected static function getMicrotime()
  {
    $timearray = explode(" ", microtime());
    return ($timearray[1] + $timearray[0]);
  }
}