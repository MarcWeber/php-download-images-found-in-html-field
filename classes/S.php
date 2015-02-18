

class S {
  static public function starts_with($s1, $s2){
    return substr($s1, 0, strlen($s2)) == $s2;
  }

  static public function ends_with($s1, $s2){
    return substr($s1, strlen($s1) - strlen($s2)) == $s2;
  }
}
