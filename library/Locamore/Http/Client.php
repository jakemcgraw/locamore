<?php
class Locamore_Http_Client
{
  
  const BLANK_RESOURCE_TYPE = 'blank';
  
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
      if ($response->isSuccessful()) {
        $contentType = $response->getHeader('Content-type');
        switch($contentType) {
          
          // This is how you should deliver JSON
          case 'application/json':
            // ... continue down ...
            
          // This is how Google Maps delivers JSON
          case 'text/javascript; charset=UTF-8':
          
            return Zend_Json::decode($response->getBody(), Zend_Json::TYPE_OBJECT);
            
          default:
            return $response->getBody();
        }
      }
    }
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
  
}

class Locamore_Http_Client_Exception extends Zend_Exception {}