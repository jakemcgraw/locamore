<?php
class UpdateController extends Zend_Controller_Action 
{
  
  /**
   * @var Zend_Registry
   */
  protected $_reg;
  
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
  
  public function geoAction() 
  {
    $user = $this->_helper->getModel('user');
    $users = $user->getGoogleMapsGeoBatch(50);
    
    $maps = $this->_helper->getModel('google_maps');
    $maps->setResource('geo');
    $users = $maps->processBatch($users);
    
    $log = array();
    foreach($users as $idx => $data) {
      try {
        $user->save($data);
      } catch (Exception $e) {
        $log[] = implode(',', $data);
      }
    }
    $this->_logErrors('Geo', $log);
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