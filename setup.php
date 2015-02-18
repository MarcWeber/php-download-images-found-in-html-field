<?php
// common setup for script.php and test.php

require_once './config.php';

if (!in_array(substr(phpversion(), 0, 3), ['5.5', '5.6']))
  throw new Exception('php >= 5.5 required');

date_default_timezone_set('Europe/Berlin');

$mysqli = call_user_func_array([new ReflectionClass('mysqli'), 'newInstance'], $mysqli_instance_args);
if($mysqli->connect_errno > 0){
    die('Unable to connect to database [' . $mysqli->connect_error . ']');
}
$mysqli->prepare("SET sql_mode = 'STRICT_ALL_TABLES'")->execute();
$mysqli->prepare("SET NAMES 'utf8'")->execute();

{ // load classes from classes/* automatically

  function autload_class($class){

    $class = str_replace('\\', '/', $class);

    if (in_array($class , (array('parent')))) return false;
    $files = array();
    $files[] = dirname(__FILE__).'/classes/'.$class.'.php';
    foreach ($files as $f) {
      if (file_exists($f)){
        require_once $f;
        return true;
      }
    }
    return false;
  }
  spl_autoload_register('autload_class');
}

if (true)
{ // detect errors faster be overriding php's error_handler
  ErrorHandling::setup(array(
      'ERROR_CONTACT_EMAIL' => ERROR_CONTACT_EMAIL,
      'unexpected_failure_handlers' => array(array('ErrorHandling', 'example_handle_unexpected_failure'))
  ));
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


define('IMAGE_STATUS_TABLE_Q','`'.IMAGE_STATUS_TABLE.'`');
