<?php

define('ZF_PATH', realpath('/home/cowbowstyle/zend_framework/latest'));
define('LOCAMORE_AP_PATH', realpath('/home/cowbowstyle/locamore/'));
if (!defined('ENV_VAR')) {
  define('ENV_VAR', 'development');
}

ini_set('include_path', ini_get('include_path')
  . PATH_SEPARATOR . ZF_PATH
  . PATH_SEPARATOR . LOCAMORE_AP_PATH . '/models'
  . PATH_SEPARATOR . LOCAMORE_AP_PATH . '/library'
);

// Use Zend_Loader for autoloading
require_once 'Zend/Loader.php';
Zend_Loader::registerAutoload();

// **** Registry
$registry = Zend_Registry::getInstance();

// **** Configuration file
$config = new Zend_Config_Ini(LOCAMORE_AP_PATH.'/config/default.ini', ENV_VAR);
$registry->config = $config;

// **** Error reporting, handling
ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOCAMORE_AP_PATH.'/logs/error');
if (!function_exists('exceptions_error_handler')) {
  function exceptions_error_handler ($severity, $errstr, $errfile, $errline) {
    // Suppressed error
    if (error_reporting() === 0) {
      return;
    }
    throw new ErrorException($errstr, 0, $severity, $errfile, $errline);
  }
}
set_error_handler('exceptions_error_handler');

// **** Timezone
date_default_timezone_set('UTC');
if (isset($config->timezone)) {
  @date_default_timezone_set($config->timezone);
}

// **** Database
if (isset($config->database)) {
  Zend_Db_Table_Abstract::setDefaultAdapter(
    Zend_Db::factory($config->database)
  );
}

// **** Layout
Zend_Layout::startMvc(array(
  'layout' => 'default',
  'layoutPath' => LOCAMORE_AP_PATH.'/views/layouts',
  'mvcSuccessfulActionOnly' => true
));

// **** Routes
$router = new Zend_Controller_Router_Rewrite();
if (isset($config->routes)) {
  $router->addConfig($config->routes);
}

Zend_Controller_Action_HelperBroker::addPrefix('Locamore_Helper');

$front = Zend_Controller_Front::getInstance();
if (isset($config->baseurl)) {
// **** Base URL
  $front->setBaseUrl($config->baseurl);
}
$front->setRouter($router)
  ->setControllerDirectory(LOCAMORE_AP_PATH.'/controllers')
  ->setParam('useDefaultControllerAlways', true)
  ->dispatch();