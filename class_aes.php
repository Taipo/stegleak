<?php

namespace AES;

class AES {
  public static function encrypt( $string, $key, $a = null, $tag_length = 128 ) {
    $key_length = mb_strlen( $key, '8bit');
    $cipher_type    = self::set_method();
    $iv             = self::get_iv();
    if ( !is_null( $a ) && !empty( $a ) ) {
      $a = hex2bin( $a );
    } else $a = null;
    self::assert_inputs( $string, $key, $key_length, $iv, $a, $tag_length );
    $ciphertext_raw = ( false !== strpos( self::set_method(), 'GCM' ) ) ? trim( openssl_encrypt( $string, $cipher_type, $key, OPENSSL_RAW_DATA, $iv, $tag, ( null === $a ? '' : $a ), $tag_length / 8 ) ) : trim( openssl_encrypt( $string, $cipher_type, $key, OPENSSL_RAW_DATA, $iv ) );
    $hmac           = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    $output         = ( false !== strpos( self::set_method(), 'GCM' ) ) ? base64_encode( $tag . $iv . $hmac . $ciphertext_raw ) : base64_encode( $iv . $hmac . $ciphertext_raw );
    return $output;
  }
  public static function decrypt( $data, $key, $a = null, $tag_length = 128 ) {
    $cipher_type        = self::set_method();
    $ciphertext_dec    = base64_decode( $data );
    if ( false !== strpos( self::set_method(), 'GCM' ) ) {
      $tag_length = $tag_length / 8;
      $tag                = substr( $ciphertext_dec, 0, $tag_length );
    }
    if ( !is_null( $a ) && !empty( $a ) ) {
      $a = hex2bin( $a );
    } else $a = null;
    $ivlen              = openssl_cipher_iv_length( $cipher = $cipher_type );
    $iv                 = substr( $ciphertext_dec, $tag_length, $ivlen );
    $hmac               = substr( $ciphertext_dec, $ivlen + $tag_length, $sha2len = 32 );
    $ciphertext_raw     = substr( $ciphertext_dec, $ivlen + $sha2len + $tag_length );
    $original_plaintext = ( false !== strpos( self::set_method(), 'GCM' ) ) ? trim( openssl_decrypt( $ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, ( null === $a ? '' : $a ) ) ) : trim( openssl_decrypt( $ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv ) );
    $calcmac            = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    if ( hash_equals( $hmac, $calcmac ) ) {
      return $original_plaintext;
    } else throw new Exception( 'Invalid params: Failed hash_equals()' );
  }
  public static function get_iv() {
       return openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::set_method() ) );
  }
  public static function is_binary( $str ) {
      return preg_match( '~[^\x20-\x7E\t\r\n]~', $str ) > 0;
  }
  public static function set_method( $block_size = 256, $mode = 'CBC' ) {
      if ( phpversion() > 7.0 ) $mode = 'GCM';
      if ( phpversion() < 6 ) {
        throw new Exception( 'Insecure version of PHP!' );
      }
      return 'AES-' . $block_size . '-' . $mode;
  }
  /**
   * 
   * @return cryptographically safe string of bytes
   */
  public static function get_key( $hashlen = 32 ) {
    for( $x=0; $x<=100; $x++ ) {
      $key = openssl_random_pseudo_bytes( $hashlen, $strong );
      if ( false !== $strong ) {
        break;
      }
    }
    return $key;
  }
  public static function assert_inputs( $string, $key, $key_length, $iv, $a, $tag_length  ) {
        assert_options( ASSERT_ACTIVE, 1 );
        assert_options( ASSERT_WARNING, 1 );
        assert_options( ASSERT_BAIL, true );
        assert_options( ASSERT_QUIET_EVAL, 1 );
        
        assert_options( ASSERT_CALLBACK, array( 'self', 'assert_handler' ) );
    
        assert( ( is_null( $string ) || is_string( $string ) ), 'The data to encrypt must be null or a binary string.' );
        assert( ( self::is_binary( $key ) ), 'The key must be a binary string.' );
        assert( is_string( $key ), 'The key encryption key must be a binary string.' );
        assert( in_array( $key_length, array( 128, 192, 256 ) ), 'Bad key encryption key length.' );
        assert( is_string( $iv ), 'The Initialization Vector must be a binary string.' );
        assert( ( is_null( $a ) || self::is_binary( $a ) ), 'The Additional Authentication Data must be null or a binary string.' );
        assert( is_int( $tag_length ), 'Invalid tag length. Supported values are: 128, 120, 112, 104 and 96.' );
        assert( in_array( $tag_length, array( 128, 120, 112, 104, 96 ) ), 'Invalid tag length. Supported values are: 128, 120, 112, 104 and 96.' );
  }
  function assert_handler( $file, $line, $message ) {
      echo "<hr>Assertion Failed:
          File '$file'<br />
          Line '$line'<br />
          Code '$message'<br /><hr />";
          exit;
  }  
}
?>
