<?php
include "class_aes.php";
include "class_facesteg.php";

header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

use AES\AES;

$detector = new Face_Steg( 'detection.dat',5 );
$image_name = 'Estella_01.png';
$detector->face_detect( $image_name );

$secret_text = 'IP: ' . $_SERVER[ 'REMOTE_ADDR' ] . "\n";

# do crypto
$key             = AES::get_key( 256 ); // for the purposes of testing, autogen a hard passcode
$a = 'feedfacedeadbeeffeedfacedeadbeefabaddad2'; // this is added for future use for example 2fa
$cryptostring    = AES::encrypt( $secret_text, $key, $a );

# save the passcode and encrypted text somewhere
$file_entry = "Image Name: ". $image_name . "\nPass Code: " . base64_encode( $key ) . "\nEmbedded Encrypted String:\n" . $cryptostring;
$fp = fopen( 'passcode.txt', 'w' );
fwrite( $fp, $file_entry );
fclose( $fp );

# create the image
$detector->toStegPNG( $cryptostring );
exit;
