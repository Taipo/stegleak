<?php
include "class_aes.php";
include "class_facesteg.php";

header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-type: text/plain');

$AES = new AES( 256, 'CBC' );
$detector = new Face_Steg('detection.dat',5);
$detector->face_detect('Encoded_01.png');
$password = 'add pass code here';
$decoded = $AES->decrypt( $detector->toStegMSG(), base64_decode( $password ) );
echo "Decoded MSG: " . ( ( strlen( $decoded  ) > 0 ) ? $decoded : 'Error: Incorrect Pass Code!' );
exit;
