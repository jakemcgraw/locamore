<?php
class Model_Yahoo_Maps extends Locamore_Http_Client {
  
  // Service codes
  const Y_GEO_STATUS_SUCCESS              = 200;
  const Y_GEO_STATUS_BAD_REQUEST          = 400;
  const Y_GEO_STATUS_FORBIDDEN            = 403;
  const Y_GEO_STATUS_SERVICE_UNAVAILABLE  = 503;
  
  // Standard codes
  const STATUS_SUCCESS                    = 200;
  const STATUS_TOO_MANY_QUERIES           = 403;
  
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
      $user['geo'] = 1;
      $data = $result->ResultSet->Result;
      $user['lon'] = $data->Longitude;
      $user['lat'] = $data->Latitude;
      if (!empty($data->City)) {
        $user['g_city'] = $data->City;
      }
      if (!empty($data->Country) && $data->Country == 'US') {
        if (!empty($data->State)) {
          $user['g_us_state'] = $data->State;          
        }
        if (!empty($data->Zip)) {
          $user['g_us_zipcode'] = $data->Zip;
        }
      }
    }
    
    return $user;
  }
}