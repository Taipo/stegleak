<?php
// error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));
set_time_limit(0);
include "class_facesteg.php";

header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$detector = new Face_Steg('detection.dat',5);
$image_name = 'Estella_01.png';
$detector->face_detect( $image_name );

$secret_text = 'secret message';

# do crypto
$pass_code = $detector->getHash( 86 ); // for the purposes of testing, autogen a hard passcode
$cryptostring    = $detector->do_crypto( $secret_text, $pass_code );

# save the passcode and encrypted text somewhere
$file_entry = "Image Name: ". $image_name . "\nPass Code: " . $pass_code . "\nEmbedded Encrypted String:\n" . $cryptostring;
$fp = fopen( 'passcode.txt', 'w' );
fwrite( $fp, $file_entry );
fclose( $fp );

# create the image
$detector->toStegPNG( $cryptostring );
exit;
