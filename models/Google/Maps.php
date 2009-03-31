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
  protected $_statusTooManyQueries        = self::STATUS_G_GEO_TOO_MANY_QUERIES;
  
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
    self::STATUS_G_GEO_SUCCESS =>
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
    if ($result->Status->code != self::STATUS_G_GEO_SUCCESS) {
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
    
    static $accuracyMessage = array(
       'Unknown'
       , 'Country level accuracy'
       , 'Region (state, province, prefecture, etc.) level accuracy'
       , 'Sub-region (county, municipality, etc.) level accuracy'
       , 'Town (city, village) level accuracy'
       , 'Post code (zip code) level accuracy'
       , 'Street level accuracy'
       , 'Intersection level accuracy'
       , 'Address level accuracy'
       , 'Premise (building name, property name, shopping center, etc.) level accuracy'
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
      // Default to invalid address
      $user['geo'] = 0;
      
      try {
        $data = $result->Placemark[0];
        $user['lon'] = $data->Point->coordinates[0];
        $user['lat'] = $data->Point->coordinates[1];

        // Usable lat and lon, everything else is gravy
        $user['geo'] = 1;
        
        $sub = false;
        if (isset($data->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea)) {
          $sub = true;
        }
        
        switch($data->AddressDetails->Accuracy) {
          case 9:
          case 8:
          case 7:
          case 6:

          // Postal Code Available
          case 5:
            $user['g_postal_code'] = $sub 
              ? $data->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->PostalCode->PostalCodeNumber
              : $data->AddressDetails->Country->AdministrativeArea->Locality->PostalCode->PostalCodeNumber;
              
            // ... continue down ...

          // Locality available
          case 4:
            
            $user['g_city'] = $sub 
              ? $data->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->LocalityName
              : $data->AddressDetails->Country->AdministrativeArea->Locality->LocalityName;
              
            // ... continue down ...

          // Region available
          case 3:
          case 2:
            $user['g_region'] = $data->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName;
            // ... continue down ...
            
          case 1:
            $user['g_country_code'] = $data->AddressDetails->Country->CountryNameCode;
            break;
        }
      } catch (Exception $e) {
        // Do nothing
      }
    }
    
    return $user;
  }
}