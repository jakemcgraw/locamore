<?php
class Model_Google_Maps extends Locamore_Http_Client {
  
  // Service codes
  const STATUS_G_GEO_SUCCESS              = 200;
  const STATUS_G_GEO_INVALID_REQUEST      = 400;
  const STATUS_G_GEO_SERVER_ERROR         = 500;
  const STATUS_G_GEO_MISSING_QUERY        = 601;
  const STATUS_G_GEO_UNKNOWN_ADDRESS      = 602;
  const STATUS_G_GEO_UNAVAILABLE_ADDRESS  = 603;
  const STATUS_G_GEO_BAD_KEY              = 610;
  const STATUS_G_GEO_TOO_MANY_QUERIES     = 620;
  
  // Standard codes
  const STATUS_SUCCESS                    = 200;
  const STATUS_TOO_MANY_QUERIES           = 620;
  
  // Error codes
  protected $_errorStatus = array(
    self::STATUS_G_GEO_INVALID_REQUEST
    , self::STATUS_G_GEO_SERVER_ERROR
    , self::STATUS_G_GEO_MISSING_QUERY
    , self::STATUS_G_GEO_UNKNOWN_ADDRESS
    , self::STATUS_G_GEO_UNAVAILABLE_ADDRESS
    , self::STATUS_G_GEO_BAD_KEY
    , self::STATUS_G_GEO_TOO_MANY_QUERIES
  );
  
  // Status messages
  protected $_statusMessages = array(
    self::Y_GEO_STATUS_SUCCESS =>
      'Success'
    , self::STATUS_G_GEO_INVALID_REQUEST =>
      'Invalid client request'
    , self::STATUS_G_GEO_SERVER_ERROR =>
      'A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is unknown.'
    , self::STATUS_G_GEO_MISSING_QUERY =>
      'An empty address was specified in the HTTP q parameter.'
    , self::STATUS_G_GEO_UNKNOWN_ADDRESS =>
      'No corresponding geographic location could be found for the specified address, possibly because the address is relatively new, or because it may be incorrect.'
    , self::STATUS_G_GEO_UNAVAILABLE_ADDRESS =>
      'The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons.'
    , self::STATUS_G_GEO_BAD_KEY =>
      'The given key is either invalid or does not match the domain for which it was given.'
    , self::STATUS_G_GEO_TOO_MANY_QUERIES =>
      'The given key has gone over the requests limit in the 24 hour period or has submitted too many requests in too short a period of time. If you\'re sending multiple requests in parallel or in a tight loop, use a timer or pause in your code to make sure you don\'t send the requests too quickly.'
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
  
  protected function _isError($result) 
  {
    if (parent::_isError($result)) {
      return true;
    }
    
    // Google sends code with in message body
    if ($result->Status->code != self::STATUS_SUCCESS) {
      $this->_lastResponse['status'] = $result->Status->code;
      return true;
    }
    
    return false;
  }
  
  public function processUserBatch(array $batch, $key = 'city', $param = 'q', $ttl = 3) 
  {
    return $this->_processBatch($batch, $key, $param, $ttl, array($this, '_processUserBatchResult'));
  }
  
  protected function _processUserBatchResult($user, $result)
  {
    static $invalidAddressStatus = array(
      self::STATUS_G_GEO_MISSING_QUERY
      , self::STATUS_G_GEO_UNKNOWN_ADDRESS
      , self::STATUS_G_GEO_UNAVAILABLE_ADDRESS
    );
    
    // Error processing result
    if (null === $result) {
      
      // No geographic information available for request
      if (in_array($this->_lastResponse['status'], $invalidAddressStatus)) {
        $user['geo'] = 0;
        $user['lon'] = null;
        $user['lat'] = null;
      
      // Server error, try again later
      } else {
        $user['geo'] = null;
      }
    } else {
      $user['geo'] = 1;
      $data = $result->Placemark[0];
      $user['lon'] = $data->Point->coordinates[0];
      $user['lat'] = $data->Point->coordinates[1];
      $user['g_city'] = $data->AddressDetails->Country->AdministrativeArea->Locality->LocalityName;
      if ($data->AddressDetails->Country->CountryNameCode == 'US') {
        $user['g_us_state'] = $data->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName;
        $user['g_us_zipcode'] = $data->AddressDetails->Country->AdministrativeArea->Locality->PostalCode->PostalCodeNumber;
      }
    }
    
    return $user;
  }
}