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

class Face_Steg {
  protected $detection_data;
  protected $canvas;
  protected $face;
  private $reduced_canvas;
  private $max_duration;

  public function __construct($detection_file = 'detection.dat',$max_duration=5) {
    if (is_file($detection_file)) {
      $this->detection_data = unserialize(file_get_contents($detection_file));
    } else {
      throw new Exception("Couldn't load detection data");
    }
    $this->max_duration = $max_duration;
  }

  public function face_detect($file) {
    if (is_resource($file)) {
      $this->canvas = $file;
    } elseif (is_file($file)) {
      $this->canvas = imagecreatefrompng($file);
    } else {
      throw new Exception("Can not load $file");
    }
    
    $im_width = imagesx($this->canvas);
    $im_height = imagesy($this->canvas);

    $ratio = 0;
    $diff_width = 320 - $im_width;
    $diff_height = 240 - $im_height;
    if ($diff_width > $diff_height) {
      $ratio = $im_width / 320;
    } else {
      $ratio = $im_height / 240;
    }

    if ($ratio != 0) {
      $this->reduced_canvas = imagecreatetruecolor($im_width / $ratio, $im_height / $ratio);
      imagecopyresampled($this->reduced_canvas, $this->canvas, 0, 0, 0, 0, $im_width / $ratio, $im_height / $ratio, $im_width, $im_height);
      
      $stats = $this->get_img_stats($this->reduced_canvas);
      $this->face = $this->do_detect_greedy_big_to_small($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
      if ($this->face['w'] > 0) {
        $this->face['x'] *= $ratio;
        $this->face['y'] *= $ratio;
        $this->face['w'] *= $ratio;
      }
    } else {
      $stats = $this->get_img_stats($this->canvas);
      $this->face = $this->do_detect_greedy_big_to_small($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
    }
    return ($this->face['w'] > 0);
  }

  protected function _asc2hex($x) {
    $s=''; foreach(str_split($x) as $c) { $s.=sprintf("%02X",ord($c)); } return($s);
  }
  protected function _hex2asc($x) {
    $s=''; foreach(explode("\n",trim(chunk_split($x,2))) as $h) { $s.=chr(hexdec($h)); } return($s);
  }
  protected function _bin2asc($str) {
    $data = "";
    $len = strlen($str);
    for ($i=0;$i<$len;$i+=8) { $ch=chr(bindec(substr($str,$i,8))); if(!ord($ch))break; $data.=$ch; }
    return $data;
  }
  protected function _asc2bin($str) {
    $data = "";
    $len = strlen($str);
    for($i=0;$i<$len;$i++) { $data.=str_pad(decbin(ord($str[$i])),8,'0',STR_PAD_LEFT); }
    return $data.'00000000';
  }
  protected function _getcolor($img,$x,$y) {
    $color = imagecolorat($img,$x,$y);
    return array('R'=>($color>>16)&0xFF,'G'=>($color>>8)&0xFF,'B'=>$color&0xFF);
  }
  protected function _setcolor($img,$r,$g,$b) {
    $c=imagecolorexact($img,$r,$g,$b); if($c!=-1)return $c;
    $c=imagecolorallocate($img,$r,$g,$b); if($c!=-1)return $c;
    return imagecolorclosest($img,$r,$g,$b);
  }

  public function toPNG() {
    $color = imagecolorallocate($this->canvas, 255, 0, 255); //PINK
    imagerectangle($this->canvas, $this->face['x'], $this->face['y'], $this->face['x']+$this->face['w'], $this->face['y']+ $this->face['w'], $color);
    header('Content-type: image/png');
    imagepng($this->canvas);
  }

  public function toStegPNG($message) {
    $bits=$this->_asc2bin($message);
    $lenbit=strlen($bits);
    $nx=($this->face['x']+$this->face['w']);
    $ny=($this->face['y']+$this->face['w']);
    for($x=$this->face['x'],$bit=0; $x<$nx; $x++) {
      for($y=$this->face['y']; $y<$ny; $y++) {
        $pix=$this->_getcolor($this->canvas,$x,$y);
        foreach(array('R','G','B') as $C) {
          $col[$C]=$bit<$lenbit?($pix[$C]|$bits[$bit])&(254|$bits[$bit++]):$pix[$C];
        }
        imagesetpixel($this->canvas,$x,$y,$this->_setcolor($this->canvas,$col['R'],$col['G'],$col['B']));
      }
    }
    header('Content-type: image/png');
    imagepng($this->canvas);
  }

  public function toStegMSG() {
    $data = "";
    $nx=($this->face['x']+$this->face['w']);
    $ny=($this->face['y']+$this->face['w']);
    for($x=$this->face['x'],$bit=0; $x<$nx; $x++) {
      for($y=$this->face['y']; $y<$ny; $y++) {
        $pix=$this->_getcolor($this->canvas,$x,$y);
        $data.=($pix['R']&1).($pix['G']&1).($pix['B']&1);
      }
    }
    return $this->_bin2asc($data);
  }

  public function toJson() {
    return json_encode($this->face);
  }
  
  public function getFace() {
    return $this->face;
  }
  
  protected function get_img_stats($canvas){
    $image_width = imagesx($canvas);
    $image_height = imagesy($canvas);
    $iis = $this->compute_ii($canvas, $image_width, $image_height);
    return array(
      'width' => $image_width,
      'height' => $image_height,
      'ii' => $iis['ii'],
      'ii2' => $iis['ii2']
    );
  }
  
  protected function compute_ii($canvas, $image_width, $image_height ){
    $ii_w = $image_width+1;
    $ii_h = $image_height+1;
    $ii = array();
    $ii2 = array();
                            
    for($i=0; $i<$ii_w; $i++ ){
      $ii[$i] = 0;
      $ii2[$i] = 0;
    }
                                
    for($i=1; $i<$ii_h-1; $i++ ){
      $ii[$i*$ii_w] = 0;
      $ii2[$i*$ii_w] = 0;
      $rowsum = 0;
      $rowsum2 = 0;
      for($j=1; $j<$ii_w-1; $j++ ) {
        $rgb = ImageColorAt($canvas, $j, $i);
        $red = ($rgb >> 16) & 0xFF;
        $green = ($rgb >> 8) & 0xFF;
        $blue = $rgb & 0xFF;
        $grey = ( 0.2989*$red + 0.587*$green + 0.114*$blue ) >> 0;
        $rowsum += $grey;
        $rowsum2 += $grey*$grey;
        
        $ii_above = ($i-1)*$ii_w + $j;
        $ii_this = $i*$ii_w + $j;
        
        $ii[$ii_this] = $ii[$ii_above] + $rowsum;
        $ii2[$ii_this] = $ii2[$ii_above] + $rowsum2;
      }
    }
    return array('ii'=>$ii, 'ii2' => $ii2);
  }
  
  protected function do_detect_greedy_big_to_small( $ii, $ii2, $width, $height ){
    $s_w = $width/20.0;
    $s_h = $height/20.0;
    $start_scale = $s_h < $s_w ? $s_h : $s_w;
    $scale_update = 1 / 1.2;
    $startTimestamp = time();
    for($scale = $start_scale; $scale > 1; $scale *= $scale_update ){
      $actualTimestamp = time();
      if(($actualTimestamp - $startTimestamp) < $this->max_duration) {
        $w = (20*$scale) >> 0;
        $endx = $width - $w - 1;
        $endy = $height - $w - 1;
        $step = max( $scale, 2 ) >> 0;
        $inv_area = 1 / ($w*$w);
        for($y = 0; $y < $endy ; $y += $step ){
          for($x = 0; $x < $endx ; $x += $step ){
            $passed = $this->detect_on_sub_image( $x, $y, $scale, $ii, $ii2, $w, $width+1, $inv_area);
            if($passed) {
              return array('x'=>$x, 'y'=>$y, 'w'=>$w);
            }
          }
        }
      } else {
        break;
      }
    }
    return null;
  }
  
  protected function detect_on_sub_image( $x, $y, $scale, $ii, $ii2, $w, $iiw, $inv_area){
    $mean = ( $ii[($y+$w)*$iiw + $x + $w] + $ii[$y*$iiw+$x] - $ii[($y+$w)*$iiw+$x] - $ii[$y*$iiw+$x+$w] )*$inv_area;
    $vnorm = ( $ii2[($y+$w)*$iiw + $x + $w] + $ii2[$y*$iiw+$x] - $ii2[($y+$w)*$iiw+$x] - $ii2[$y*$iiw+$x+$w] )*$inv_area - ($mean*$mean);
    $vnorm = $vnorm > 1 ? sqrt($vnorm) : 1;
    
    $passed = true;
    for($i_stage = 0; $i_stage < count($this->detection_data); $i_stage++ ){
      $stage = $this->detection_data[$i_stage];
      $trees = $stage[0];

      $stage_thresh = $stage[1];
      $stage_sum = 0;
                        
      for($i_tree = 0; $i_tree < count($trees); $i_tree++ ){
        $tree = $trees[$i_tree];
        $current_node = $tree[0];
        $tree_sum = 0;
        while( $current_node != null ){
          $vals = $current_node[0];
          $node_thresh = $vals[0];
          $leftval = $vals[1];
          $rightval = $vals[2];
          $leftidx = $vals[3];
          $rightidx = $vals[4];
          $rects = $current_node[1];
          
          $rect_sum = 0;
          for( $i_rect = 0; $i_rect < count($rects); $i_rect++ ){
              $s = $scale;
              $rect = $rects[$i_rect];
              $rx = ($rect[0]*$s+$x)>>0;
              $ry = ($rect[1]*$s+$y)>>0;
              $rw = ($rect[2]*$s)>>0;
              $rh = ($rect[3]*$s)>>0;
              $wt = $rect[4];
              
              $r_sum = ( $ii[($ry+$rh)*$iiw + $rx + $rw] + $ii[$ry*$iiw+$rx] - $ii[($ry+$rh)*$iiw+$rx] - $ii[$ry*$iiw+$rx+$rw] )*$wt;
              $rect_sum += $r_sum;
          }
           
          $rect_sum *= $inv_area;
               
          $current_node = null;
          if( $rect_sum >= $node_thresh*$vnorm ){
            if( $rightidx == -1 ) {
              $tree_sum = $rightval;
            } else {
              $current_node = $tree[$rightidx];
            }
          } else {
            if( $leftidx == -1 ) {
              $tree_sum = $leftval;
            } else {
              $current_node = $tree[$leftidx];
            }
          }
        }
        $stage_sum += $tree_sum;
      }
      if( $stage_sum < $stage_thresh ){
        return false;
      }
    }
    return true;
  }
  function do_crypto( $string, $pass_code, $padding = 0, $offset = 0 ) {
    $cipher_type    = ( substr( phpversion(), 0, 1 ) > 6 ) ? 'AES-256-CTR' : 'AES-256-GCM';
    $key            = pack( 'H*', $this->hashMake( 'sha512', $pass_code, false ) );
    $ivlen          = openssl_cipher_iv_length( $cipher = $cipher_type );
    $iv             = openssl_random_pseudo_bytes( $ivlen );
    $timestamp		= "\x00" . time() . $offset;
    $padding        = ( strlen( $padding > 0 ) ) ? "\x00\x00" . openssl_random_pseudo_bytes( $padding ) : '';
    $ciphertext_raw = openssl_encrypt( $string . $timestamp . $padding, $cipher, $key, OPENSSL_RAW_DATA, $iv );
    $hmac           = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    return base64_encode( $iv . $hmac . $ciphertext_raw );
  }
  function do_decrypto( $ciphertext_base64, $pass_code ) {
    $cipher_type        = ( substr( phpversion(), 0, 1 ) > 6 ) ? 'AES-256-CTR' : 'AES-256-GCM';
    $key                = pack( 'H*', $this->hashMake( 'sha512', $pass_code ) );
    $ciphertext_dec     = base64_decode( $ciphertext_base64 );
    $ivlen              = openssl_cipher_iv_length( $cipher = $cipher_type );
    $iv                 = substr( $ciphertext_dec, 0, $ivlen );
    $hmac               = substr( $ciphertext_dec, $ivlen, $sha2len = 32 );
    $ciphertext_raw     = substr( $ciphertext_dec, $ivlen + $sha2len );
    $original_plaintext = openssl_decrypt( $ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv );
    $calcmac            = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
    if ( hash_equals( ( string )$hmac, ( string )$calcmac ) ) {
      $plaintext = substr( $original_plaintext, 0, strpos( $original_plaintext, "\x00" ) );
    return $plaintext;
    }
  }
  function getHash( $hashlen ) {
    $hash = substr( $this->translation_str(), 0, $hashlen );
    $thishash = $this->hashMake( $this->hashType, $hash );
  return $hash;
  }
  function hashMake( $sType, $input, $withSalt = false ) {
    $hashtype = $this->hashType( $sType );
    // format: algorithm:iterations:salt:hash
    if ( false !== $withSalt ) $pepper = base64_encode( ( ( function_exists( 'random_bytes' ) ) ? random_bytes( 64 ) : openssl_random_pseudo_bytes( 64 ) ) );
    return hash( $hashtype, $this->pbkdf2( $hashtype, $input, ( ( false !== $withSalt ) ? $pepper : NULL ), 4096, 24, true ) );
  }
  function hashType( $sType ) {
    foreach ( hash_algos() as $key => $val ) {
      if ( $key == $sType ) {
        return $val;
      }
    }
    return;
  }
  function pbkdf2( $algorithm, $password, $salt, $count, $key_length, $raw_output = false ) {
    $algorithm = strtolower( $algorithm );
    if ( !in_array( $algorithm, hash_algos(), true ) )
      die( 'PBKDF2 ERROR: Invalid hash algorithm.' );
    if ( $count <= 0 || $key_length <= 0 )
      die( 'PBKDF2 ERROR: Invalid parameters.' );
    $hash_length = strlen( hash( $algorithm, "", true ) );
    $block_count = ceil( $key_length / $hash_length );
    $output      = "";
    for ( $i = 1; $i <= $block_count; $i++ ) {
      // $i encoded as 4 bytes, big endian.
      $last = $salt . pack( "N", $i );
      // first iteration
      $last = $xorsum = hash_hmac( $algorithm, $last, $password, true );
      // perform the other $count - 1 iterations
    for ( $j = 1; $j < $count; $j++ ) {
      $xorsum ^= ( $last = hash_hmac( $algorithm, $last, $password, true ) );
    }
      $output .= $xorsum;
    }
    if ( $raw_output )
      return substr( $output, 0, $key_length );
    else
    return bin2hex( substr( $output, 0, $key_length ) );
  }
  function htmlspecialchars_recode( $text ) {
    return strtr( $text, array_flip( get_html_translation_table( HTML_SPECIALCHARS ) ) );
  }
  function translation_str() {
    # reseed every request
    mt_srand( $this->kakano_tupokanoa() );
    $translation_table = array_merge( range( '0', '9' ), range( 'A', 'Z' ), range( 'a', 'z' ) );
    shuffle( $translation_table );
    $tt1 = implode( '', $translation_table );
    shuffle( $translation_table );
    $tt2 = implode( '', $translation_table );
    shuffle( $translation_table );
    $tt3 = implode( '', $translation_table );    
    return $tt1 . $tt2 . $tt3;
  }
  # random seed
  function kakano_tupokanoa() {
    $ran_string = abs( crc32( $this->hashMake( $this->hashType, ( hexdec( substr( sha1( microtime() ), -8 ) ) & 0x7fffffff ), true ) ) ); // setting true calls MCRYPT_DEV_URANDOM
    return ord( substr( $ran_string, 0, 1 ) ) << 24 | ord( substr( $ran_string, 1, 1 ) ) << 16 | ord( substr( $ran_string, 2, 1 ) ) << 8 | ord( substr( $ran_string, 3, 1 ) );
  }  
}
?>
