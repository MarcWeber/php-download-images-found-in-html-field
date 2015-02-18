<?php

require_once './setup.php';

try {
  function qn($s){ return "`$s`"; }

  function my_log($msg){
    file_put_contents(LOG_FILE, sprintf("%s %s\n", date('d.m.Y H:i:s') , $msg), FILE_APPEND | LOCK_EX);
  }

  function log_parsing_problem($opts){
    my_log(json_encode(A::values_at($opts, ['table', 'field', 'key_fields', 'message', 'html'])));
  }

  function debug($s){
    echo "$s\n";
  }

  try {
    $create_sql = " 
    CREATE TABLE ".IMAGE_STATUS_TABLE_Q." (
    id int(10) auto_increment PRIMARY KEY NOT NULL,
    md5_of_url varchar(255) NOT NULL,
    ext varchar(10) NOT NULL,
    image_url varchar(255) NOT NULL,
    tries int(10) NOT NULL default 0,
    first_try datetime NULL,
    last_try datetime NULL,
    download_ok enum('Y', 'N') default 'N',
    last_error varchar(255) NULL
    ) engine = MYISAM default character set = utf8 collate = utf8_general_ci
    ";

    $stmt = $mysqli->prepare($create_sql);
    $stmt->execute();

  } catch (Exception $e) {
    // probably table already exists, ignore
    if (!preg_match('/table .*already exists/i', $e->getMessage())){
      throw $e;
    }
  }

  $md5_to_id = [];
  foreach($mysqli->query('SELECT md5_of_url, id, ext, download_ok, last_try, tries FROM '.IMAGE_STATUS_TABLE_Q) as $row){
    $md5_to_id[$row['md5_of_url']] = $row;
  }
  $stmt_insert = $mysqli->prepare('INSERT INTO '.IMAGE_STATUS_TABLE_Q.'
    (md5_of_url, ext, image_url, first_try)
    VALUES
    (?, ?, ?, NOW())
  ');

  $stmt_update_message = $mysqli->prepare('UPDATE '.IMAGE_STATUS_TABLE_Q.' SET last_try = NOW(), tries = tries + 1, last_error = ? WHERE md5_of_url = ?');
  $stmt_update_ok = $mysqli->prepare('UPDATE '.IMAGE_STATUS_TABLE_Q.' SET download_ok = "Y" WHERE md5_of_url = ?');

  // FOR EACH TABLE & FIELD CONFIG
  foreach ($database_columns_to_process as $cfg) {
    $stmt_update_html = $mysqli->prepare(
      'UPDATE '.qn($cfg['table']).' 
       SET '.qn($cfg['field']).'=? 
       WHERE '.qn($cfg['field']).'= ? 
            AND  ('.implode(' ) AND (', array_map(function($x){return qn($x).'=?';}, $cfg['key_fields']))
               .')');

    $fs = $cfg['key_fields'];
    $fs[] = $cfg['field'];
    $fs_quoted = implode(',', array_map('qn', $fs));

    // FOR EACH ROW
    foreach ($mysqli->query("SELECT $fs_quoted FROM `".$cfg['table'].'`') as $v){
      $keys = A::values_at($v, $cfg['key_fields']);
      debug("keys: ".implode($keys));

      $html = $v[$cfg['field']];
      // DomDocument fails parsing almost everything, don't even attempt to use it
      $doc = new DOMDocument;
      $doc->strictErrorChecking = false;
      $doc->preserveWhiteSpace = true;
      try {
        $doc->loadXML($html);
      } catch (Exception $e) {
        log_parsing_problem(array_replace($cfg, ['message' => $e->getMessage(), 'keys' => $keys, 'html' => $html]));
      }
      $xpath = new DOMXpath($doc);
      $dirty = false;

      // FIND IMAGES
      foreach ($xpath->query("//img") as $el) {
        $src = $el->getAttribute("src");
        if (download_url($src)){
          $md5 = md5($src);
          $path_parts = pathinfo(preg_replace('/\?.*/', '', $src));
          $ext = $path_parts['extension'];

          if (null === $x = A::get_or($md5_to_id, $md5, null)){
            // ensure its in cache array and db
            if (is_null($x)) {
              debug("inserting $src $md5");
              $src_short = mb_substr($src,0,230);
              $stmt_insert->bind_param('sss', $md5, $ext, $src_short); // UTF-8 stuff could be longer
              $stmt_insert->execute();
              $x = ['ext' => $ext, 'id' => $stmt_insert->insert_id, 'download_ok' => 'N', 'last_try' => null, 'tries' => 0];
              $md5_to_id[$md5] =& $x;
            }
          }

          $skip_reason = null;
          if ($x['download_ok'] == 'Y')
            $skip_reason = "on disk";
          elseif ($x['last_try'] == 'this_run')
            $skip_reason = "already tried";
          elseif (!is_null($x['last_try']) && strtotime($x['last_try']) + 60 * 60 * 24 * /* retry every 2 days */ 2 < time())
            $skip_reason = "last_try too recent";
          if (!is_null($skip_reason)){
            debug("skipping download of $src for reason: $skip_reason");
          } else {
            $store_path = store_path($x['id'], $x['ext']);
            debug("store-path is $store_path, fetching $src");
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $src);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            $response = curl_exec($ch);

            $curl_err = curl_errno($ch);

            unset($curl_msg);
            if (200 != $code = curl_getinfo($ch, CURLINFO_HTTP_CODE)){
              $curl_msg = "status: $code";
            }elseif ($curl_err){
              $curl_msg = curl_error($ch);
            }
            if (isset($curl_msg)){
              debug("fetch error: $curl_msg for $src");
              $err_short = mb_substr($curl_msg, 0, 230);
              $stmt_update_message->bind_param('ss', $err_short, $md5);
              $stmt_update_message->execute();
              $x['last_try'] = 'this_run';
            } else {
              debug("writing file $store_path");
              file_put_contents($store_path, $response);
              $stmt_update_ok->bind_param('s', $md5);
              $stmt_update_ok->execute();
              $x['download_ok'] = 'Y';
            }
            curl_close($ch);
          } // attempt downloading ..

          if ($x['download_ok'] == 'Y') {
            $dirty = true;
            $new_url = new_url($x['id'], $x['ext']);
            $el->setAttribute("src", $new_url);
          }
          unset($x);
        }
      }
      if ($dirty){
        $html_new = $doc->saveXML();
        $args = array_merge([$html_new, $html], array_values($keys));
                                        # ^ only update if the HTML wasn't changed meanwhile !
        array_unshift($args, implode(array_map(function($x){return 's';}, $args)));
        MS::bind_param($stmt_update_html, $args);
        $stmt_update_html->execute();
      }
    }
  }

} catch (Exception $e) {
  uncaught_exception(['exception' => $e]);
}
