<?php
class Locamore_Http_Client
{
  
  const BLANK_RESOURCE_TYPE = 'blank';
  const BATCH_TIME_LIMIT = 20;
  const BATCH_DELAY_INTERVAL = 2;
  protected $_statusSuccess = 200;
  protected $_statusTooManyQueries = 400;
  
  /**
   * @var string
   */
  protected $_config;
  
  /**
   * @var Zend_Http_Client
   */
  protected $_client;
  
  /**
   * @var string
   */
  protected $_baseurl;
  
  /**
   * @var string
   */
  protected $_resource;
  
  /**
   * @var string
   */
  protected $_resourceType;
  
  /**
   * @var array
   */
  protected $_params = array();
  
  /**
   * @var array
   */
  protected $_validResourcePatterns = array();
  
  /**
   * @var array
   */
  protected $_validParams = array();
  
  public function __construct($config=null) 
  {
    if (null === $config) {
      $config = Zend_Registry::get('config');
      if (empty($this->_config) || !isset($config->{$this->_config})) {
        throw new Locamore_Http_Client_Exception('Invalid or missing configuration');
      }
      $config = $config->{$this->_config}->toArray();
    }
    $this->_client = new Zend_Http_Client();
    foreach($config as $name => $args) {
      $method = 'set'.ucwords($name);
      if (method_exists($this, $method)) {
        call_user_func(array($this, $method), $args);
      }
    }
  }
  
  public function __call($method, $args) 
  {
    if (strpos('get', $method) === 0) {
      $property = '_'.strtolower(substr($method,3));
      if (property_exists($this, $property)) {
        return clone $this->{$property};
      }
      return null;
    }
  }
  
  public function setBaseurl($baseurl=null) 
  {
    if ($baseurl !== null) {
      $this->_baseurl = $baseurl;
      $this->_updateClient();
    }
    return $this;
  }
  
  public function setParams() 
  {
    $num = func_num_args();
    $arg = func_get_args();
    $params = array();
    if ($num > 1) {
      if (is_string($arg[0])) {
        $params[$arg[0]] = $arg[1];
      }
    } else {
      $params = $arg[0];
    }
    if (is_array($params)) {
      foreach($params as $param => $value) {
        if (in_array($param, $this->_validParams)) {
          $this->_params[$param] = $value;
        }
      }
    }
    $this->_updateClient();
    return $this;
  }
  
  public function setResource($resource=null) 
  {
    if ($resource !== null) {
      if (empty($this->_validResourcePatterns)) {
        $this->_resource = preg_replace('/(^\/|\/$)/', '', $resource);
        $this->_resourceType = self::BLANK_RESOURCE_TYPE;
        $this->_updateClient();
        return $this;
      }
      foreach($this->_validResourcePatterns as $type => $pattern) {
        if (preg_match('/^\/?'.$pattern.'\/?$/', $resource)) {
          $this->_resource = preg_replace('/(^\/|\/$)/', '', $resource);
          $this->_resourceType = $type;
          $this->_updateClient();
          return $this;
        }
      }
    }
    throw new Locamore_Http_Client_Exception('Invalid resource ('.$resource.')');
  }
  
  public function request() 
  {
    if ($this->_client !== null) {
      
      $response = $this->_client->request();
      
      $this->_lastResponse = array(
        'status'    => $response->getStatus()
        , 'message' => $response->getMessage()
        , 'body'    => $response->getBody()
      );
      
      $contentType = $response->getHeader('Content-type');
      $contentType = preg_replace('/; charset.*$/', '', $contentType);
      switch($contentType) {
        // XML
        case 'text/xml':
          $result = @simplexml_load_string($this->_lastResponse['body']);
          break;
        
        // JSON
        case 'application/json':
        case 'text/javascript':
          $result = Zend_Json::decode($this->_lastResponse['body'], Zend_Json::TYPE_OBJECT);
          break;
        
        // PHP 
        case 'text/php':
          $result = (object) unserialize($this->_lastResponse['body']);
          break;
        
        // TEXT
        default:
          $result = $this->_lastResponse['body'];
          break;
      }
      
      if ($this->_isError($result)) {
        return null;
      }
      
      return $this->_processResult($result);
    }
    
    $this->_lastResponse = null;
    return null;
  }
  
  protected function _updateClient() 
  {
    if ($this->_client !== null) {
      if ($this->_baseurl !== null && $this->_resource !== null) {
        $this->_client->setUri(
          $this->_baseurl.'/'.$this->_resource
        );
      }
      if (is_array($this->_params)) {
        $this->_client->setParameterGet($this->_params);
      }
    }
  }
  
  protected function _isError($result) 
  {
    if ($result === null || $result === false) {
      return true;
    }
    
    if (in_array($this->_lastResponse['status'], $this->_errorStatus)) {
      return true;
    }
    
    return false;
  }
  
  protected function _processResult($result) 
  {
    return $result;
  }
  
  protected function _processBatch(array $batch, $key, $param, $ttl = 4, $callback = null) 
  {
    if ($ttl > 1) {
      set_time_limit(self::BATCH_TIME_LIMIT);
    } else {
      return null;
    }
    if (null === $callback) {
      $callback = array($this, '_processBatchResult');
    }
    $retry = array();
    foreach($batch as $idx => $data) {
      $delay = 0;
      if (!isset($data[$key])) {
        continue 1;
      }
      $this->setParams($param, $data[$key]);
      $result = $this->request();
      if (null === $result) {
        // Retry on too many queries
        if ($this->_lastResponse 
          && $this->_lastResponse['status'] === $this->_statusTooManyQueries
        ) {
          $retry[$idx] = $data;
          sleep(self::BATCH_DELAY_INTERVAL);
          continue 1;
        }
      }
      $batch[$idx] = call_user_func($callback, $data, $result);
    }
    if (!empty($retry)) {
      $result = $this->_processBatch($retry, $key, $param, --$ttl, $callback);
      if (null !== $result) {
        foreach($result as $idx => $entry) {
          $batch[$idx] = $entry;
        }
      }
    }
    return $batch;
  }
  
  protected function _processBatchResult($data, $result) 
  {
    return $data;
  }
}

class Locamore_Http_Client_Exception extends Zend_Exception {}