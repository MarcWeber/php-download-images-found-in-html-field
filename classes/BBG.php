<?php

class BBG{

    // target size
    //$target_size_typ
    //  b-200 : Breite (in Pixeln)
    //  h-400 : Höhe (in Pixeln)
    //  a-100 : Fläche (in Pixeln ^2), entspricht Fläche von BreitexHöhe = 10x10
    //  --- : Keine Änderung
    static public function calc($breite, $hoehe, $target_size_str){
        $target_size = substr($target_size_str,2);
        $target_size_typ = substr($target_size_str,0,1);
        switch($target_size_typ) {
            case 'w':
                // Breite Vorgegeben 
                $new_breite = $target_size;
                $new_hoehe = round($new_breite * $hoehe / $breite);
                break;
            case 'h':
                // Höhe vorgeben
                $new_hoehe = $target_size;
                $new_breite = round($new_hoehe * $breite / $hoehe);
            break;
            case 'a':
                // Fläche Vorgegeben 
                $new_hoehe = sqrt($target_size * $target_size / $breite * $hoehe);
                $new_breite = round($new_hoehe * $breite / $hoehe);
            break;
            case 'f':
              list($w,$h) = explode('X', $target_size);
              list($new_breite,$new_hoehe) = self::calc($breite, $hoehe, 'h-'.$h);
              if ($new_breite > $w || $new_hoehe > $h){
                list($new_breite,$new_hoehe) = self::calc($breite, $hoehe, 'w-'.$w);
              }
            break;
            case '-':
                $new_breite = $breite; $new_hoehe = $hoehe;
            break;
            default:
                throw new Exception("unbekanntes Bildgroesse!:".$target_size_typ);
        }
        return array((int) $new_breite, (int) $new_hoehe);
    }

    // result is always a jpeg
    static public function resizeImage($image_path, $target_path, $target_size,
        $also_enlarge = false, $symlink = false, $jpg_quality = 85){
        $size = getimagesize($image_path); // [0] = breite [1] = hoehe , ...
        list($b,$h) = self::calc($size[0], $size[1], $target_size);

        $d = dirname($target_path);
        if (!is_dir($d))
          mkdir($d,'0755',true);

        if ( (!($b > $size[0] || $h > $size[1]))  // nicht grösser 
            || $also_enlarge
            || !in_array(pathinfo($image_path,PATHINFO_EXTENSION), array("jpg","jpeg")) // Bild kein jpeg 
            ){
            // recreate
            $img_orig = imagecreatefromstring(file_get_contents($image_path));
            $img_dest = imagecreatetruecolor($b, $h);
            imagecopyresampled($img_dest, $img_orig, 0, 0, 0, 0, $b, $h, $size[0], $size[1]);
            // save
            imagejpeg($img_dest, $target_path, $jpg_quality);
        } else {
            // eventually create symlink only to reduce storage size
            if ($symlink)
                symlink($target_path, $image_path); // both should be absolute paths
            else
                copy($image_path, $target_path);
        }

        if (!file_exists($target_path))
            throw new Exception( 'target image '.$target_path." could'nt be created");

        return $size;
    }

}
