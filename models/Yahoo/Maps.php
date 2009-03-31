<?php
class Model_Yahoo_Maps extends Locamore_Http_Client {
  
  // Service codes
  const Y_GEO_STATUS_SUCCESS              = 200;
  const Y_GEO_STATUS_BAD_REQUEST          = 400;
  const Y_GEO_STATUS_FORBIDDEN            = 403;
  const Y_GEO_STATUS_SERVICE_UNAVAILABLE  = 503;
  
  // Standard codes
  protected $_statusTooManyQueries        = self::Y_GEO_STATUS_FORBIDDEN;
  
  // Error codes
  protected $_errorStatus = array(
    self::Y_GEO_STATUS_BAD_REQUEST, self::Y_GEO_STATUS_FORBIDDEN, self::Y_GEO_STATUS_SERVICE_UNAVAILABLE
  );
  
  // Status messages
  protected $_statusMessages = array(
    self::Y_GEO_STATUS_SUCCESS =>
      'Success'
    , self::Y_GEO_STATUS_BAD_REQUEST => 
      'Bad request. The parameters passed to the service did not match as expected. The Message should tell you what was missing or incorrect. '
    , self::Y_GEO_STATUS_FORBIDDEN => 
      'Forbidden. You do not have permission to access this resource, or are over your rate limit.'
    , self::Y_GEO_STATUS_SERVICE_UNAVAILABLE => 
      'Service unavailable. An internal problem prevented us from returning data to you.'
  );
  
  /**
   * @var string
   */
  protected $_config = 'yahoomaps';
  
  /**
   * @var array
   */
  protected $_validResourcePatterns = array(
    'geo' => 'geocode'
  );
  
  /**
   * @var array
   */
  protected $_validParams = array(
    'appid', 'street', 'city', 'state', 'zip', 'location', 'output'
  );
  
  /**
   * @var array
   */
  protected $_lastResponse = array(
    'status' => 0, 'message' => '', 'body' => ''
  );

  public function processUserBatch(array $batch, $key = 'city', $param = 'location', $ttl = 3) 
  {
    return $this->_processBatch($batch, $key, $param, $ttl, array($this, '_processUserBatchResult'));
  }

  protected function _processUserBatchResult($user, $result)
  {
    // Error processing result
    if (null === $result) {
    
      // No geographic information available for request
      if ($this->_lastResponse['status'] === self::Y_GEO_STATUS_BAD_REQUEST) {
        $user['geo'] = 0;
        $user['lon'] = null;
        $user['lat'] = null;
    
      // Server error, try again later
      } else {
        $user['geo'] = null;
      }
    } else {
      $user['geo'] = 0;
      try {
        if (isset($result->ResultSet['Result'][0])) {
          $data = (object) $result->ResultSet['Result'][0];
        } else {
          $data = (object) $result->ResultSet['Result'];
        }
        $user['lon'] = $data->Longitude;
        $user['lat'] = $data->Latitude;
        $user['geo'] = 1;
        switch($data->precision) {
          case 'address':
          case 'street':
          case 'zip+4':
          case 'zip+2':

          // Postal code available (maybe?)
          case 'zip':
            if (!empty($data->Zip)) {
              $user['g_postal_code'] = $data->Zip;
            }
            // ... continue down ...

          // Locality available
          case 'city':
            $user['g_city'] = $data->City;
            // ... continue down ...

          case 'state':
            $user['g_region'] = $data->State;
            // ... continue down ...

          case 'country':
            $user['g_country_code'] = $data->Country;
            break;
        }
      } catch (Exception $e) {
        // Do nothing
      }
    }
    
    return $user;
  }
}