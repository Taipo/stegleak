<?php
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
//
// @Author Karthik Tharavaad
// karthik_tharavaad@yahoo.com
// @Contributor Maurice Svay
// maurice@svay.Com
// @Contributor Estella Mystagic
// twitter.com/Mystagic

class AES {
  private $blocksize = 256;
  private $mode = 'CBC';

  public function __construct( $blocksize, $mode ) {
    $this->set_method( $blocksize, $mode );
  }
  function encrypt( $string, $key ) {
    if ( false === $this->validate_params( $string ) ) throw new Exception( 'Invlid params!' );
    $cipher_type    = $this->method;
    $iv             = $this->get_iv();
    $ciphertext_raw = ( false !== strpos( $this->method, 'GCM' ) ) ? trim( openssl_encrypt( $string, $cipher_type, $key, OPENSSL_RAW_DATA, $iv, $tag ) ) : trim( openssl_encrypt( $string, $cipher_type, $key, OPENSSL_RAW_DATA, $iv ) );
    $hmac           = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    $output         = base64_encode( $iv . $hmac . $ciphertext_raw );
    return ( false !== strpos( $this->method, 'GCM' ) ) ? base64_encode( $tag ) . ',' . $output : $output;
  }
  function decrypt( $data, $key ) {
    if ( false === $this->validate_params( $data ) ) throw new Exception( 'Invlid params!' );
    $cipher_type        = $this->method;
    if ( false !== strpos( $this->method, 'GCM' ) ) {
      $get_data           = explode( ',', $data );
      $tag                = base64_decode( $get_data[ 0 ] );
      $ciphertext_dec     = base64_decode( $get_data[ 1 ] );
    } else {
      $ciphertext_dec     = base64_decode( $data );
    }
    $ivlen              = openssl_cipher_iv_length( $cipher = $cipher_type );
    $iv                 = substr( $ciphertext_dec, 0, $ivlen );
    $hmac               = substr( $ciphertext_dec, $ivlen, $sha2len = 32 );
    $ciphertext_raw     = substr( $ciphertext_dec, $ivlen + $sha2len );
    $original_plaintext = ( false !== strpos( $this->method, 'GCM' ) ) ? trim( openssl_decrypt( $ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag ) ) : trim( openssl_decrypt( $ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv ) );
    $calcmac            = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    if ( hash_equals( $hmac, $calcmac ) ) {
      return $original_plaintext;
    }
  }
  protected function get_iv() {
       return openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->method ) );
   }  
  public function validate_params( $data ) {
      if ( $data != null && $this->method != null ) {
          return true;
      } else {
          return FALSE;
      }
  }
  public function set_method( $blockSize = 256, $mode = 'CBC' ) {
      if ( phpversion() > 7.0 ) $mode = 'GCM';
      if ( phpversion() < 6 ) {
        $this->method = null;
        throw new Exception( 'Insecure version of PHP!' );
      }
      $this->method = 'AES-' . $blockSize . '-' . $mode;
  }  
  /**
   * 
   * @return cryptographically safe string of bytes
   */
  function get_key( $hashlen = 49 ) {
    return openssl_random_pseudo_bytes( $hashlen );
  }
}
?>
