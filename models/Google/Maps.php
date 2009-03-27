<?php

class Model_Google_Maps extends Locamore_Http_Client {
  
  const STATUS_G_GEO_SUCCESS              = 200;
  const STATUS_G_GEO_INVALID_REQUEST      = 400;
  const STATUS_G_GEO_SERVER_ERROR         = 500;
  const STATUS_G_GEO_MISSING_QUERY        = 601;
  const STATUS_G_GEO_UNKNOWN_ADDRESS      = 602;
  const STATUS_G_GEO_UNAVAILABLE_ADDRESS  = 603;
  const STATUS_G_GEO_BAD_KEY              = 610;
  const STATUS_G_GEO_TOO_MANY_QUERIES     = 620;
  
  protected $_errorMessages = array(
    self::STATUS_G_GEO_INVALID_REQUEST =>
      'Invalid client request'
      
    , self::STATUS_G_GEO_SERVER_ERROR =>
      'A geocoding or directions request could not be successfully processed, 
      yet the exact reason for the failure is unknown.'
       
    , self::STATUS_G_GEO_MISSING_QUERY =>
      'An empty address was specified in the HTTP q parameter.'
      
    , self::STATUS_G_GEO_UNKNOWN_ADDRESS =>
      'No corresponding geographic location could be found for the specified address, 
      possibly because the address is relatively new, or because it may be incorrect.'
      
    , self::STATUS_G_GEO_UNAVAILABLE_ADDRESS =>
      'The geocode for the given address or the route for the given directions query 
      cannot be returned due to legal or contractual reasons.'
      
    , self::STATUS_G_GEO_BAD_KEY =>
      'The given key is either invalid or does not match the domain for which it was given.'
      
    , self::STATUS_G_GEO_TOO_MANY_QUERIES =>
      'The given key has gone over the requests limit in the 24 hour period or has submitted 
      too many requests in too short a period of time. If you\'re sending multiple requests 
      in parallel or in a tight loop, use a timer or pause in your code to make sure you don\'t 
      send the requests too quickly.'
  );
  
  /**
   * @var string
   */
  protected $_config = 'googlemaps';
  
  /**
   * @var array
   */
  protected $_validResourcePatterns = array(
    'geo' => 'geo'
  );
  
  /**
   * @var array
   */
  protected $_validParams = array(
    'q', 'key', 'sensor', 'output', 'oe', 'll', 'spn', 'gl'
  );
  
  /**
   * @var array
   */
  protected $_lastResponse = array(
    'status' => 0, 'message' => '', 'body' => ''
  );
  
  public function request() 
  {
    if ($this->_client !== null) {
      $response = $this->_client->request();
      $this->_lastResponse = array(
        'status' => $response->getStatus()
        , 'message' => $response->getMessage()
        , 'body' => $response->getBody()
      );
      if ($response->isSuccessful()) {
        $contentType = $response->getHeader('Content-type');
        switch($contentType) {
        
          // This is how you should deliver JSON
          case 'application/json':
            // ... continue down ...
          
          // This is how Google Maps delivers JSON
          case 'text/javascript; charset=UTF-8':
            
            $result = Zend_Json::decode($response->getBody(), Zend_Json::TYPE_OBJECT);
            
            // G_GEO Error
            if ($result->Status->code != self::STATUS_G_GEO_SUCCESS) {
              $this->_lastResponse['status']  = $result->Status->code;
              $this->_lastResponse['message'] = $this->_errorMessages[$result->Status->code];
              return null;
            }
            
            return $result;
          
          default:
            return $response->getBody();
        }
      }
    }
    return null;
  }
  
  public function processBatch(array $batch, $key = 'city', $ttl = 4) {
    
    // Add time to the clock or exit
    if ($ttl > 1) {
      set_time_limit(20);
    } else {
      return null;
    }
    
    $retry = array();
    foreach($batch as $idx => $entry) {
      $delay = 0;
      $this->setParams('q', $entry[$key]);
      $result = $this->request();
      
      // Error with request
      if (null === $result) {
        switch($this->_lastResponse['status']) {
          
          // Denied by Google, delay and add to retry queue
          case self::STATUS_G_GEO_TOO_MANY_QUERIES:
            $retry[$idx] = $entry;
            $delay = 2;
            break;
          
          // Invalid address, don't attempt another lookup
          case self::STATUS_G_GEO_MISSING_QUERY:
          case self::STATUS_G_GEO_UNKNOWN_ADDRESS:
          case self::STATUS_G_GEO_UNAVAILABLE_ADDRESS:
            $batch[$idx]['lon'] = null;
            $batch[$idx]['lat'] = null;
            $batch[$idx]['geo'] = 0;
            break;

          // Don't update record, try again another time
          default:
            break;
        }
      
      // Successful request
      } else {
        list($log, $lat, $zenith) = $result->Placemark[0]->Point->coordinates;
        $batch[$idx]['lon'] = $log;
        $batch[$idx]['lat'] = $lat;
        $batch[$idx]['geo'] = 1;
      }
      
      if ($delay > 0) {
        sleep($delay);
      }
    }
    
    // Retry requests
    if (!empty($retry)) {
      $result = $this->processBatch($retry, $key, --$ttl);
      if (null !== $result) {
        foreach($result as $idx => $entry) {
          $batch[$idx] = $entry;
        }
      }
    }
    
    return $batch;
  }
}