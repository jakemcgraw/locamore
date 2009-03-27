<?php
class Model_Etsy 
{
  
  const CONFIG = 'etsy';
  
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
  
  protected static $validResourcePatterns = array(
    'users'     => 'users\/(keywords\/\w+|\w+)'
    , 'shops'   => 'shops\/(featured|keywords\/\w+|\w+(?:\/listings(?:\/featured)?)?)'
    , 'server'  => 'server\/(?:epoch|ping)'
    , 'method'  => ''
  );
  
  protected static $validParams = array(
    'api_key', 'limit', 'offset', 'detail_level'
  );
  
  public function __construct($config=null) 
  {
    if ($config === null) {
      $config = Zend_Registry::get('config')->{self::CONFIG}->toArray();
      if (!isset($config)) {
        throw new Model_Etsy_Exception('Invalid or missing configuration');
      }
    }
    $this->_client = new Zend_Http_Client();
    foreach($config as $name => $args) {
      $method = 'set'.ucwords($name);
      if (method_exists($this, $method)) {
        call_user_func(array($this, $method), $args);
      }
    }
  }
  
  public function __call($method, $args) {
    if (strpos('get', $method) === 0) {
      $property = '_'.strtolower(substr($method,3));
      if (property_exists($this, $property)) {
        return clone $this->{$property};
      }
      return null;
    }
  }
  
  public function setBaseurl($baseurl=null) {
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
        if (in_array($param, self::$validParams)) {
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
      foreach(self::$validResourcePatterns as $type => $pattern) {
        if (preg_match('/^\/?'.$pattern.'\/?$/', $resource)) {
          $this->_resource = preg_replace('/(^\/|\/$)/', '', $resource);
          $this->_resourceType = $type;
          $this->_updateClient();
          return $this;
        }
      }
    }
    throw new Model_Etsy_Exception('Invalid resource ('.$resource.')');
  }
  
  public function request() {
    if ($this->_client !== null) {
      $response = $this->_client->request();
      if ($response->isSuccessful()) {
        $contentType = $response->getHeader('Content-type');
        switch($contentType) {
          case 'application/json':
            return Zend_Json::decode($response->getBody(), Zend_Json::TYPE_OBJECT);
          default:
            return $request->getBody();
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

class Model_Etsy_Exception extends Zend_Exception {}