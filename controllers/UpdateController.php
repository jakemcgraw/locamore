<?php
class UpdateController extends Zend_Controller_Action 
{
  
  /**
   * @var Zend_Registry
   */
  protected $_reg;
  
  protected $_services = array(
    'google' => array('limit' => 11), 'yahoo' => array('limit' => 4)
  );
  
  public function init() 
  {
    $this->_reg = Zend_Registry::getInstance();
  }
  
  public function indexAction() 
  {
    return $this->_forward('run', null, null, array(
      'ttl' => 3
    ));
  }
  
  public function runAction() 
  {
    $model = $this->_helper->getModel('job');
    $result = $model->runNextJob();
    $log = array();
    if ($result['success']) {
      $model = $this->_helper->getModel('user');
      $report = $model->batchSave($result['result']->results, $result['job_id']);
      // No failures
      if ($report['failure'] == 0) {
        $ttl = (int) $this->_request->getParam('ttl', 0);
        if ($ttl > 1) {
          return $this->_forward('run', null, null, array(
            'ttl' => ($ttl - 1)
          ));
        }
        exit;
      }
      // Error Batch
      $error = 'Batch';
      foreach($report['message'] as $entry) {
        $log[] = "{$entry['data']['user_id']},{$entry['data']['user_name']},{$entry['error']}";
      }
    } else {
      // Error Request
      $error = 'Request';
      $log[] = $result['code'].','.$result['message'];
    }
    $this->_logErrors($error, $log);
    exit;
  }
  
  public function geoCodeAction() 
  {
    // Get service name
    $service = $this->_request->getParam('service', 'google');
    if (!in_array($service, array_keys($this->_services))) {
      $service = 'google';
    }
    $limit = $this->_services[$service]['limit'];
    
    // Get user batch to process
    $users  = $this->_helper->getModel('user');
    $batch  = $users->getGeoBatch($limit);
    
    // Process user batch (GeoCode)
    $geo    = $this->_helper->getModel($service.'_maps');
    $batch  = $geo->processUserBatch($batch);
    
    var_dump($batch);
    exit;
    
    // Save result
    foreach($batch as $idx => $data) {
      try {
        $users->save($data);
      } catch (Exception $e) {
        $log[] = '\''.implode('\',\'', $data).'\'';
      }
    }
    
    // Log any errors
    $this->_logErrors('Geo Code '.strtoupper($service), $log);
    
    if (null === $this->_request->getParam('halt')) {
      $service = ($service == 'google') ? 'yahoo' : 'google';
      return $this->_forward('geo-code', null, null, array(
        'service' => $service, 'halt' => true
      ));
    }
    exit;
  }
  
  protected function _logErrors($error, array $log) 
  {
    if (!empty($log)) {
      array_unshift($log, '['.date('Y-m-d H:i:s').'] '.$error);
      file_put_contents(LOCAMORE_AP_PATH.'/logs/job_error', implode("\n", $log)."\n", FILE_APPEND);
    }
  }
}