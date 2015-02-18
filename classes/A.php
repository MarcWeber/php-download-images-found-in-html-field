<?php

// array related helpers
class A {

  static public function get_or(array $a, $k, $d = null){
    return array_key_exists($k, $a) ? $a[$k] : $d;
  }
  static public function assert_key(array &$a, $k){
    if (!array_key_exists($k, $a))
      throw new Exception('array does not contain key '.$k);
  }

  static public function ensure(array &$a, $k, $v){
    if (!array_key_exists($k, $a))
      $a[$k] = $v;
  }

  static public function values_at($a, $keys){
    $r = [];
    foreach ($keys as $k) {
      $r[$k] = $a[$k];
    }
    return $r;
  }

}
