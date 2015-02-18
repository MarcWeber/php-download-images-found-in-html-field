<?php 

class MS {

  static public function &to_ref($x){
    $y = unserialize(serialize($x));
    return $y;
  }

  static public function bind_param($stmt, $args){
    // I HATE PHP - code taken from http://php.net/manual/de/mysqli-stmt.bind-param.php
    $ref = new \ReflectionClass("mysqli_stmt");
    $method = $ref->getMethod("bind_param");
    for($i = 0; $i < count($args); $i++){
      $args[$i] = (string) $args[$i];
    }
    $method->invokeArgs($stmt, $args);
  }
}
