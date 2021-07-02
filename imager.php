<?php
/**
 * Imager, simply manipulate pictures. 
 * Version: 1.14
 *
 *  Changelog:
 *    1.15 ( 2013-09-19 )
 *     * fix: transparent picture and adding a not transparent pic
 *    1.14 ( 2013-07-07 )
 *     * fixes
 *    1.13 ( 2012-02-19 )
 *     * Optimization in: blur, blurgaussian, brightness, contrast, gamma,
 *        grayscale, negate, sepia, sketch, smooth
 *     * addAlpha: if you add alpha channel picture use that alpha, else the
 *        color component will be used
 *     + add new function: toPalette( $maxColor, $dither )
 *     + add new function: getChannel ( R-G-B, HSL, HSV, HSI ... )
 *     + add new function: getHistogramData
 *     + add new function: makeHistogram
 *     + add new function: transparency
 *     + add new function: createFromArray
 *     + add new function: imagefilterhueadjustment
 *    1.12 ( 2011-12-30 )
 *     * Lots of bugfixes with transparency images ( watermark, resize, rotate)
 *     + code optimizations
 *    1.11 ( 2011-10-30 )
 *     + add some PHPDoc comment
 *     + addImage function
 *     + add exception to the save/load functions
 *     * fix an input image error in darkness effect
 *     + new effects: saturation, noise, scatter, pixelate
 * 
 * @author    Jenei Viktor Attila
 * @copyright 2010-2011 Jenei Viktor Attila
 * @package   Imager
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @filesource
 */

abstract class imgLoaderSaver {
	protected $filename;
	abstract public function load( );
	abstract public function save( &$imgres );
}

class Channel {
	const RGB = 0;
	const RED = 1;
	const GREEN = 2;
	const BLUE = 3;
	const ALPHA = 4;
	const HSL = 5;
	const HSV = 6;
	const HSI = 7;
	const HSL2 = 8;
	const HSP = 9;
	const CCIR601 = 10;
	const ITU_R = 11;
}

class JPEG extends imgLoaderSaver {
	protected $quality = 85;

	public function __construct( $filename, $quality = 85 ) {
		$this->filename = $filename;
		$this->quality = $quality;
	}

	public function load( ) {
		if ( file_exists( $this->filename ) ) {
			return imagecreatefromjpeg( $this->filename );
		}
		return null;
	}

	public function save( &$imgres ) {
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
		if ( !imagejpeg( $imgres, $this->filename, $this->quality ) ) {
			throw new Exception( 'notwriteable' );
		}
	}
}

class PNG extends imgLoaderSaver {
	protected $quality = 6;

	public function __construct( $filename, $quality = 6 ) {
		$this->filename = $filename;
		$this->quality = $quality;
	}

	public function load( ) {
		if ( file_exists( $this->filename ) ) {
			$res = imagecreatefrompng( $this->filename );
			return $res;
		} 
		return null;
	}

	public  function save( &$imgres ) {
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
		if ( !imagepng( $imgres, $this->filename, $this->quality ) ) {
			throw new Exception( 'notwriteable' );
		}
	}
}

class GIF extends imgLoaderSaver {
	public function __construct( $filename ) {
		$this->filename = $filename;
	}

	public function load( ) {
		if ( file_exists( $this->filename ) ) {
			return imagecreatefromgif( $this->filename );
		} 
		return null;
	}

	public  function save( &$imgres ) {
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
		if ( !imagegif( $imgres, $this->filename ) ) {
			throw new Exception( 'notwriteable' );
		}
	}
}

class Canvas extends imgLoaderSaver {
	protected $width  = 0;
	protected $height = 0;
	protected $color  = 0;
	
	public function __construct( $width, $height, $fillcolor = array( 0, 0, 0) ) {
		$this->width = $width;
		$this->height= $height;
		$this->color = $fillcolor;
	}

	public function load( ) {
		$t = imagecreatetruecolor( $this->width, $this->height );
		$col = imagecolorallocate( $t, $this->color[0], $this->color[1], $this->color[2] );
		imagefilledrectangle( $t, 0, 0, $this->width, $this->height, $col );
		return $t;
	}

	public  function save( &$imgres ) {
	}
}

class AutoFileInput extends imgLoaderSaver {
	public function __construct( $filename ) {
		$this->filename = $filename;
	}

	public function load( ) {
		if ( file_exists( $this->filename ) ) {
			$image_info = getimagesize( $this->filename );
			$img = null;
			switch( $image_info[2] ) {
				case 1:
					$img = new GIF( $this->filename ) ;
					break;
				case 2:
					$img = new JPEG( $this->filename ) ;
					break;
				case 3:
					$img = new PNG( $this->filename ) ;
					break;
			}
			if ( $img != null ) {
				return $img->load( );
			}
		} 
		throw new Exception( 'notsupportedimage' );
	}

	public  function save( &$imgres ) {
	}
}

class Transparency {
	public $transparent = false;
	public $alphachannel = false;
	public $color = array( 255, 255, 255 );

	public function __construct( $typeof = null ) {
		if ( $typeof != null ) {
			if ( is_array( $typeof ) ) {
				$this->transparent = true;
				$this->color = $typeof;
			} else if ( is_bool( $typeof ) ) {
				$this->transparent = true;
				$this->alphachannel = true;
			}
		}
	}
}

class Imager {
	const TOP_LEFT     = 0x00000001;
	const TOP_RIGHT    = 0x00000002;
	const BOTTOM_LEFT  = 0x00000003;
	const BOTTOM_RIGHT = 0x00000004;
	const CENTER       = 0x00000005;
	const ABSOLUTE     = 0x00000006;

	protected $fontPath = './';
	protected $properties = array( 'width', 'height' );
	protected $imgres = null;
	protected $transparencyinfo = null;

	private function rotateX( $x, $y, $theta ){
		return $x * cos( $theta ) - $y * sin( $theta );
	}

	private function rotateY( $x, $y, $theta ){
		return $x * sin( $theta ) + $y * cos( $theta );
	}

	public function __get( $k ) {
		if( array_key_exists( $k, $this->properties ) )
			return $this->properties[ $k ];
	}

	public function __set($k, $v) {
		$this->properties[$k] = $v;
	}
	
	public function getResource() {
		return $this->imgres;
	}
	
	public function getTransparencyInfo() {
		return $this->transparencyinfo;
	}
	
	public function setTransparencyInfo( $ti ) {
		$this->transparencyinfo = $ti;
	}
	
	protected function cloneTransparency( &$t, $ti ) {
		if ( $ti != null && $ti->transparent ) {
			if ( $ti->alphachannel ) {
				imagealphablending( $t, false );
				imagesavealpha( $t, true );
			} else {
				list( $r, $g, $b ) = $ti->color;
				$clr = imagecolorallocate( $t, $r, $g, $b);
				imagecolortransparent( $t, $clr );
			}
		}
	}

	public function transpColorToAlphaChannel( ) {
		$t = imagecreatetruecolor( $this->width, $this->height );
		$ti = $this->getTransparencyInfo();
		list( $ra, $ga, $ba ) = $ti->color;
		
		imagealphablending( $t, false );
		imagesavealpha( $t, true );

		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$rgb = imagecolorat( $this->imgres, $x, $y );
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				if ( $ra == $r && $ga == $g && $ba == $b ) {
					$color = imagecolorallocatealpha( $t, $r, $g, $b, 127 );
				} else {
					$color = imagecolorallocatealpha( $t, $r, $g, $b, 0 );
				}
				imagesetpixel( $t, $x, $y, $color );
			}
		}

		return new Imager( $t, new Transparency( true ) );
	}

	public function __construct( $img = null, Transparency $transp = null ) {
		$this->transparencyinfo = $transp;
		if ( !is_null( $img ) ) {
			
			if ( $img instanceof Imager ) {
				$res = $img->getResource();
				$this->imgres = clone $res;
				$this->fontPath = $img->getFontPath();
			} else if( $img instanceof imgLoaderSaver )  {
				$this->imgres = $img->load();
				if ( $this->imgres === null ) {
					throw new Exception( 'cannotload' );
				}
			} else if ( is_resource( $img ) ) {
				$this->imgres = $img;
			}
			if ( $this->imgres != null ) {
				$this->width  = imagesx( $this->imgres );
				$this->height = imagesy( $this->imgres );
			} else {
				/* throw an error */
			}
			if ( $transp != null ) {
				if ( $transp->transparent ) {
					if ( $transp->alphachannel ) {
						imagealphablending( $this->imgres, false );
						imagesavealpha( $this->imgres, true );
					} else {
						list( $r, $g, $b ) = $transp->color;
						$clr = imagecolorallocate( $this->imgres, $r, $g, $b);
						imagecolortransparent( $this->imgres, $clr );

					}
				}
			}
		} else {
			/* throw an error */
		}
	}
	
	public function __destruct() {
		if ( is_resource( $this->imgres ) )
			imagedestroy( $this->imgres );
	}

	public function cloneObject( $withImage = true ) {
		$ti = $this->getTransparencyInfo();
		$t = imagecreatetruecolor( $this->width, $this->height );
		if ( $withImage ) {
			if ( $ti != null && $ti->transparent && $ti->alphachannel ) {
				imagealphablending( $t, false );
				imagecopy( $t, $this->imgres, 0, 0, 0, 0, $this->width, $this->height);
			} else {
				imagecopymerge( $t, $this->imgres, 0, 0, 0, 0, $this->width, $this->height, 100 );
			}
		}
		$this->cloneTransparency( $t, $ti );
		return new Imager( $t, $ti );
	}

	public function setFontPath( $pathName ) {
		$this->fontPath = $pathName;
		return $this;
	}
	
	public function getFontPath() {
		return $this->fontPath;
	}
	
	public function save( $img = null ) {
		if ( !is_null( $img ) ) {
			if ( $img instanceof Imager ) {
				
			} else if( $img instanceof imgLoaderSaver ) {
				$transp = $this->getTransparencyInfo();
				if ( $transp != null && $transp->alphachannel ) {
					imagealphablending( $this->imgres, false );
					imagesavealpha( $this->imgres, true );
				}
				$img->save( $this->imgres );
			} else {
				/* throw an error */
			}
		} else {
			/* throw an error */
		}
		
		return $this;
	}

	/**
	* Fill the image with a color
	*
	* @param array $color Red-Green-Blue values
	* @return Imager
	*/
	public function fill( $color = array( 0, 0, 0 ) ) {
		$img = $this->cloneObject();
		$im = $img->imgres;
		//$this->cloneTransparency( $im, $img->getTransparencyInfo() );

		$co = imagecolorallocate( $im, $color[0], $color[1], $color[2] );
		imagefilledrectangle( $im, 0, 0, $this->width, $this->height, $co );
		return new Imager( $im, $img->getTransparencyInfo() );
	}

	/**
	* Make a thumbnail picture of image
	*
	* @param Imager $img the picture want to thumb
	* @param bool $crop true if you don't want background, default is false
	* @return Imager
	*/
	public function thumb( $img, $crop = false ) {
		if ( ! ( $img instanceof Imager ) ) $img = new Imager( $img, $this->getTransparencyInfo() );

		$scale = min( $this->height / $img->height, $this->width / $img->width );
		$nw = (int)ceil( $img->width  * $scale );
		$nh = (int)ceil( $img->height * $scale );

		while ( $nw > $this->width  ) $nw--;
		while ( $nh > $this->height ) $nh--;

		if ( $crop ) {
			return $img->resize( $nw, $nh );
		} else {
			return $this->watermark( $img->resize( $nw, $nh ), Imager::CENTER );
		}
	}

	/**
	* Add a picture to another
	*
	* @param Imager $img the picture want to add
	* @param int $x X offset
	* @param int $y Y offset
	* @return Imager
	*/
	public function addImage( $img, $x = 0, $y = 0 ) {
		if ( ! ( $img instanceof Imager ) ) $img = new Imager( $img, $this->getTransparencyInfo() );
		$w = $this->width;
		$h = $this->height;
		if ( $this->width < ( $img->width + $x ) ) {
			$w = $img->width + $x;
		}
		if ( $this->height < ( $img->height + $y ) ) {
			$w = $img->height + $y;
		}
		$t = imagecreatetruecolor( $w, $h );
		$backcolor = imagecolorallocatealpha( $t, 0, 0, 0, 127 );

		imagefill( $t, 0, 0, imagecolorallocatealpha( $t, 0, 0, 0, 127 ) );

		$im = new Imager( $t, new Transparency( true ) );
		return $im->watermark( $this, Imager::ABSOLUTE, 0, 0 )
			->watermark( $img, Imager::ABSOLUTE, $x, $y );
	}

	/**
	* Fill to image width (and size)
	*
	* @param Imager $img the picture want to fill
	* @param bool $crop true if you don't want background, default is true
	* @return Imager
	*/
	public function fillTo( $img, $crop = true ) {
		if ( ! ( $img instanceof Imager ) ) $img = new Imager( $img, $this->getTransparencyInfo() );

		$scale = min( $this->height / $img->height, $this->width / $img->width );
		if ( $crop ) {
			$scale = max( $this->height / $img->height, $this->width / $img->width );
		}
		$nw = (int)ceil( $img->width  * $scale );
		$nh = (int)ceil( $img->height * $scale );

		if ( $crop ) {
			return $img->resize( $nw, $nh )->
				crop( ( $nw - $this->width ) / 2 , ( $nh - $this->height ) / 2, $this->width, $this->height );
		} else {
			return $img->resize( $nw, $nh );
		}
	}

	public function textRectangle( $str, $fontsize = 13, $font_file = 'arial.ttf', $angle = 0 ) {
		$textsize = imageftbbox( $fontsize, $angle, $this->fontPath . '/' . $font_file, $str );
		$result = array(
			'lower_left_x'  => $textsize[0], 
			'lower_left_y'  => $textsize[1],
			'lower_right_x' => $textsize[2],
			'lower_right_y' => $textsize[3],
			'upper_right_x' => $textsize[4],
			'upper_right_y' => $textsize[5],
			'upper_left_x'  => $textsize[6],
			'upper_left_y'  => $textsize[7] );
		return $result;
	}
	
	public function drawText( $str, $x = 0, $y = 0, $shadow = false, $fontsize = 13, $angle = 0, array $fontcolor = array(), $font_file = 'arial.ttf', array $extra = array() ) {
		$img = $this->cloneObject();
		$im = $img->imgres;

		$color = imagecolorallocate( $im, 0x00, 0x00, 0x00 );
		if ( count( $fontcolor ) == 3 ) {
			$color = imagecolorallocate( $im, $fontcolor[0], $fontcolor[1], $fontcolor[2] );
		}
		if ( $shadow ) {
			$shadow_text_color = imagecolorallocate( $im, 40, 40, 40 );
			imagefttext( $im, $fontsize, $angle, $x + 1, $y + 1, $shadow_text_color, $font_file , $str);
		}
	
		imagefttext( $im, $fontsize, $angle, $x, $y, $color, $this->fontPath . '/' . $font_file, $str );
		
		return $img;
	}
	
	public function drawRectangle( $x1, $y1, $x2, $y2, array $color = array(), $linesize = 1, $filled = false ) {
		$img = $this->cloneObject();
		$im = $img->imgres;
		
		$rectcolor = imagecolorallocate( $im, 0xFF, 0xFF, 0xFF );
		if ( count( $color ) == 3 ) {
			$rectcolor = imagecolorallocate( $im, $color[0], $color[1], $color[2] );
		}
		if ( $filled ) {
			imagefilledrectangle( $im, $x1, $y1, $x2, $y2, $rectcolor );
		} else {
			imagesetthickness( $im, $linesize );
			imagerectangle( $im, $x1, $y1, $x2, $y2, $rectcolor );
		}
		return $img;
	}
	
	public function fillColorToBorder( $start_x, $start_y, array $fill_color = array( 0xFF, 0xFF, 0xFF ), array $border_color = array( 0, 0, 0 ) ) {

		$img = $this->cloneObject();
		$im = $img->imgres;

		$fill = imagecolorallocate( $im, 0xFF, 0xFF, 0xFF );	
		$border = imagecolorallocate( $im, 0, 0, 0 );
		if ( count( $fill_color ) == 3 ) {
			$fill = imagecolorallocate( $im, $fill_color[0], $fill_color[1], $fill_color[2] );
		}
		if ( count( $border_color ) == 3 ) {
			$border = imagecolorallocate( $im, $border_color[0], $border_color[1], $border_color[2] );
		}	
		imagefilltoborder( $im, $start_x, $start_y, $border, $fill );
		return $img;
	}

	public function drawEllipse( $center_x, $center_y, $width, $height, $filled = false, $linesize = 1, array $color = array() ) {
		$img = $this->cloneObject();
		
		$ocolor = imagecolorallocate( $img->imgres, 0xFF, 0xFF, 0xFF );
		if ( count( $color ) == 3 ) {
			$ocolor = imagecolorallocate( $img->imgres, $color[0], $color[1], $color[2] );
		}
		
		if ( $filled ) {
			imagefilledellipse( $img->imgres, $center_x, $center_y, $width, $height, $ocolor );
		} else {
			imagesetthickness( $img->imgres, $linesize );
			imageellipse( $img->imgres, $center_x, $center_y, $width, $height, $ocolor );
		}
		return $img;
	}

	/* source: http://hu.php.net/manual/en/function.imageline.php */
	public function drawLine( $x1, $y1, $x2, $y2, array $color = array(), $thick = 1 ) {
		/* this way it works well only for orthogonal lines
		imagesetthickness($image, $thick);
		return imageline($image, $x1, $y1, $x2, $y2, $color); */
		$img = $this->cloneObject();
	
		$icolor = imagecolorallocate( $img->imgres, 0, 0, 0 );
		if ( count( $color ) == 3 ) {
			$icolor = imagecolorallocate( $img->imgres, $color[0], $color[1], $color[2] );
		}
		if ( $thick == 1 ) {
			imageline( $img->imgres, $x1, $y1, $x2, $y2, $icolor );
		}
		$t = $thick / 2 - 0.5;
		if ( $x1 == $x2 || $y1 == $y2 ) {
			imagefilledrectangle( $img->imgres, round( min( $x1, $x2 ) - $t ), round( min( $y1, $y2 ) - $t ), round( max( $x1, $x2 ) + $t ), round( max( $y1, $y2 ) + $t ), $icolor );
			return $img;
		}
		$k = ( $y2 - $y1 ) / ( $x2 - $x1 ); //y = kx + q
		$a = $t / sqrt( 1 + pow( $k, 2 ) );
		$points = array(
			round( $x1 - ( 1 + $k ) * $a ), round( $y1 + ( 1 - $k ) * $a ),
			round( $x1 - ( 1 - $k ) * $a ), round( $y1 - ( 1 + $k ) * $a ),
			round( $x2 + ( 1 + $k ) * $a ), round( $y2 - ( 1 - $k ) * $a ),
			round( $x2 + ( 1 - $k ) * $a ), round( $y2 + ( 1 + $k ) * $a ),
		);
		imagefilledpolygon( $img->imgres, $points, 4, $icolor );
		imagepolygon( $img->imgres, $points, 4, $icolor );
		return $img;
	}

	public function drawPolygon( $image, array $points = array(), $filled = false, array $color = array(), $linesize = 1 ) {
		$img = $this->cloneObject();
		
		$icolor = imagecolorallocate( $img->imgres, 0, 0, 0 );
		if ( count( $color ) == 3 ) {
			$icolor = imagecolorallocate( $img->imgres, $color[0], $color[1], $color[2] );
		}
		$poi = array();
		foreach( $points as $k => $row ) {
			$poi[] = $row[ 'x' ];
			$poi[] = $row[ 'y' ];
		}
		if ( $filled ) {
			imagefilledpolygon( $img->imgres, $poi, count( $points ), $icolor );
		} else {
			imagesetthickness( $img->imgres, $linesize );
			imagepolygon( $img->imgres, $poi, count( $points ), $icolor );
		}
		return $img;
	}

	public function drawPolygonSoft( array $points = array(), $filled = false, array $color = array(), $linesize = 1 ) {
		$img = $this->cloneObject();
	
		foreach( $points as $k => $row ) {
			if ( $k > 0 ) {
				$img->drawLine( $points[ $k - 1 ][ 'x' ], $points[ $k - 1 ][ 'y' ], $row[ 'x' ], $row[ 'y' ], $color, $linesize );
			}
		}
		$img->drawLine( $points[ count( $points ) - 1  ][ 'x' ], $points[ count( $points ) - 1 ][ 'y' ], $points[0][ 'x' ], $points[0][ 'y' ], $color, $linesize );
		return $img;
	}

	public function drawArc( $center_x, $center_y, $width, $height, $start_degree, $end_degree, $filled = false, $linesize = 1, array $color = array() ) {
		$img = $this->cloneObject();
		
		$ocolor = imagecolorallocate( $img->imgres, 0, 0, 0 );
		if ( count( $color ) == 3 ) {
			$ocolor = imagecolorallocate( $img->imgres, $color[0], $color[1], $color[2] );
		}
		if ( $filled ) {
			imagefilledarc( $img->imgres, $center_x, $center_y, $width, $height, $start_degree, $end_degree, $ocolor, IMG_ARC_PIE);
		} else {
			imagesetthickness( $img->imgres, $linesize );
			imagearc( $img->imgres, $center_x, $center_y, $width, $height, $start_degree, $end_degree, $ocolor );
		}
		return $img;
	}

	/* http://hu.php.net/manual/en/function.imageline.php */
	protected function bezier_cubic_interpolation( $points, $steps ){
		$t = 1 / $steps;
		$temp = $t * $t;
		$ret = array();
		$f = $points[0];
		$fd = 3 * ( $points[1] - $points[0] ) * $t;
		$fdd_per_2= 3 * ( $points[0] - 2 * $points[1] + $points[2] ) * $temp;
		$fddd_per_2 = 3 * ( 3*( $points[1] - $points[2] ) + $points[3] - $points[0] ) * $temp * $t;
		$fddd = $fddd_per_2 + $fddd_per_2;
		$fdd = $fdd_per_2 + $fdd_per_2;
		$fddd_per_6 = $fddd_per_2 * ( 1.0 / 3 );
		for ( $loop=0; $loop < $steps; $loop++ ) {
			array_push( $ret, $f );
			$f = $f + $fd + $fdd_per_2 + $fddd_per_6;
			$fd = $fd + $fdd + $fddd_per_2;
			$fdd = $fdd + $fddd;
			$fdd_per_2 = $fdd_per_2 + $fddd_per_2;
		}
		return $ret;
	}
	
	public function bezier( array $points = array(), $steps, $linesize = 1, array $color = array() ) {
		$x = array();
		$y = array();
		foreach( $points as $point ) {
			array_push( $x, $point[ 'x' ] );
			array_push( $y, $point[ 'y' ] );
		}
		$bx = $this->bezier_cubic_interpolation( $x, $steps );
		$by = $this->bezier_cubic_interpolation( $y, $steps );
		$im = imagecreatetruecolor( $this->width, $this->height );
		imagecopyresampled( $im, $this->imgres, 0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height );
		$img = new Imager( $im, $this->getTransparencyInfo() );
		
		$ocolor = imagecolorallocate( $im, 233, 14, 91);
		if ( count( $color ) == 3 ) {
			$ocolor = imagecolorallocate( $im, $color[0], $color[1], $color[2] );
		}
		imagesetthickness( $im, $linesize );
		for( $i = 0; $i < $steps - 1; $i++ ) {
			imageline( $im, $bx[ $i ], $by[ $i ], $bx[ $i + 1 ], $by[ $i + 1 ], $ocolor );
		}
		return $img;
	}
	
	/**
	* Add alpha transparency layer image to the image
	*
	* @param Imager $img the picture want to use as Alpha
	* @return Imager
	*/
	public function addAlpha( $img ) {
		if ( ! ($img instanceof Imager) ) $img = new Imager( $img );
		$t = imagecreatetruecolor( $this->width, $this->height );
		imagealphablending( $t, false );

		$alphid = $img->getResource();
		$tid = $this->getResource();
		$transp = $img->getTransparencyInfo();
		for($y=0; $y < $this->height; $y++) {
			for( $x=0; $x < $this->width; $x++) {
				$alpha = 0;
				// if picture has Alpha channel, use it
				if ( $transp->alphachannel ) {
					$color = imagecolorsforindex( $alphid, imagecolorat( $alphid, $x, $y ) );
					$alpha = $color[ 'alpha' ];
				} else {
					// use the grayscaled picture blue value
					$blue = (int)( ( imagecolorat( $alphid, $x, $y ) & 0xFF ) / 2 );
					$alpha = 127 - $blue;
				}
				$rgb = imagecolorat( $tid, $x, $y );
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				$ncolor = imagecolorallocatealpha( $t, $r, $g, $b, $alpha );
				imagesetpixel( $t, $x, $y, $ncolor );
			}
		}
		return new Imager( $t, new Transparency( true ) );
	}

	/**
	* Scaleto image to width, height, or resize to a box (with the default propotion)
	*
	* @param string $method width, height, box
	* @param array $value only used when the $method is box, the array looked like as array( 100, 200 )
	* @return Imager
	*/
	public function scaleTo( $method = 'width', $value ) {
		$scale = 1;
		if ( $method == 'width' ) {
			$scale = $value / $this->width ;
		} else if ( $method == 'height' ){
			$scale = $value / $this->height;
		} else if ( $method == 'box' && is_array( $value ) ) {
			$scale1 = $value[0] / $this->width;
			$scale2 = $value[1] / $this->height;
			$scale = min( $scale1, $scale2 );
		}
		$nw = (int)ceil( $this->width  * $scale );
		$nh = (int)ceil( $this->height * $scale );

		return $this->resize( $nw, $nh );		
	}

	/**
	* Resize an image to a width-height pair
	*
	* @param int $width New width
	* @param int $height New height
	* @return Imager
	*/
	public function resize( $width, $height ) {
		$t = imagecreatetruecolor( $width, $height );
		$this->cloneTransparency( $t, $this->getTransparencyInfo() );
		imagecopyresampled( $t, $this->imgres, 0, 0, 0, 0, $width, $height, $this->width, $this->height );
		return new Imager( $t, $this->getTransparencyInfo() );
	}

	/**
	* Crop an image
	*
	* @param int $x X offset
	* @param int $y Y offset
	* @param int $width Width
	* @param int $height Height
	* @return Imager
	*/
	public function crop( $x, $y, $w, $h ) {
		if($x + $w > $this->width) $w = $this->width - $x;
		if($y + $h > $this->height) $h = $this->height - $y;
		if($w <= 0 || $h <= 0) return false;

		$t = imagecreatetruecolor( $w, $h );
		$this->cloneTransparency( $t, $this->getTransparencyInfo() );
		$ti = $this->getTransparencyInfo();
		if ( $ti != null && $ti->transparent && $ti->alphachannel ) {
			imagecopy( $t, $this->imgres, 0, 0, $x, $y, $w, $h );
		} else {
			imagecopymerge( $t, $this->imgres, 0, 0, $x, $y, $w, $h, 100 );
		}
		return new Imager( $t, $this->getTransparencyInfo() );
	}

	/**
	* Flip horizontally
	* @return Imager
	*/
	public function flipH() {
		$img = $this->cloneObject();
		imagecopyresampled( $img->imgres, $this->imgres, 0, 0, ( $this->width-1 ), 0, $this->width, $this->height, 0-$this->width, $this->height );
		return $img;
	}

	/**
	* Flip vertically
	* @return Imager
	*/
	public function flipV() {
		$img = $this->cloneObject();
		imagecopyresampled( $img->imgres, $this->imgres, 0, 0, 0, ( $this->height-1 ), $this->width, $this->height, $this->width, 0-$this->height );
		return $img;
	}

	/**
	* Rotate a picture with angle (and background color)
	* @param double $degree Rotation degree
	* @param array $color Background color (RGB), default is black
	* @return Imager
	*/
	public function rotate( $degree, $color = array( 0,0,0 ), $ignore_transparent = 0 ) {
		$img = $this->cloneObject();
		$backcolor = imagecolorallocatealpha( $img->imgres, $color[0], $color[1], $color[2], 127 );
		$t = imagerotate( $img->imgres, $degree, $backcolor, $ignore_transparent );
		if ( $ignore_transparent == 0 ) {
			imagesavealpha( $t, true );
		}
		return new Imager( $t, new Transparency( true ) );
	}

	/* very slow (getpixel, setpixel), but correct alternative to rotate pic ( source from internet ) */
	public function rotate2( $degree, $color = array( 0, 0, 0 ) ) {
		$srcImg = $this->imgres;
		$angle = $degree;
		$bgcolor = ( $color[0] << 16 ) + ( $color[1] << 8 ) + $color[2];
		$ignore_transparent = 0;
		$srcw = imagesx( $srcImg );
		$srch = imagesy( $srcImg );

		$angle %= 360;
		$angle = -$angle;

		if($angle == 0) {
			if ( $ignore_transparent == 0 ) {
				imagesavealpha( $srcImg, true );
			}
			return $srcImg;
		}

		$theta = deg2rad ( $angle );

		if ( ( abs( $angle ) == 90 ) || ( abs( $angle ) == 270 ) ) {
			$width = $srch;
			$height = $srcw;
			if ( ( $angle == 90 ) || ( $angle == -270 ) ) {
				$minX = 0;
				$maxX = $width;
				$minY = -$height+1;
				$maxY = 1;
			} else if ( ( $angle == -90 ) || ( $angle == 270 ) ) {
				$minX = -$width+1;
				$maxX = 1;
				$minY = 0;
				$maxY = $height;
			}
		} else if ( abs( $angle ) === 180 ) {
			$width = $srcw;
			$height = $srch;
			$minX = -$width+1;
			$maxX = 1;
			$minY = -$height+1;
			$maxY = 1;
		} else {
			$temp = array ( rotateX( 0, 0, 0-$theta ),
				rotateX( $srcw, 0, 0-$theta ),
				rotateX( 0, $srch, 0-$theta ),
				rotateX( $srcw, $srch, 0-$theta )
			);
			$minX = floor (min( $temp ) );
			$maxX = ceil( max( $temp ) );
			$width = $maxX - $minX;

			// the height of the destination image.
			$temp = array ( rotateY( 0, 0, 0-$theta ),
				rotateY( $srcw, 0, 0-$theta ),
				rotateY( 0, $srch, 0-$theta ),
				rotateY( $srcw, $srch, 0-$theta )
			);
			$minY = floor( min( $temp ) );
			$maxY = ceil( max( $temp ) );
			$height = $maxY - $minY;
		}

		$destimg = imagecreatetruecolor( $width, $height );
		if ( $ignore_transparent == 0 ) {
			imagefill( $destimg, 0, 0, imagecolorallocatealpha( $destimg, 255,255, 255, 127 ) );
			imagesavealpha( $destimg, true );
		}

		for( $x = $minX; $x < $maxX; $x++ ) {
			for( $y = $minY; $y < $maxY; $y++ ) {
				// fetch corresponding pixel from the source image
				$srcX = round( rotateX( $x, $y, $theta ) );
				$srcY = round( rotateY( $x, $y, $theta ) );
				if( $srcX >= 0 && $srcX < $srcw && $srcY >= 0 && $srcY < $srch ) {
					$color = imagecolorat( $srcImg, $srcX, $srcY );
				} else {
					$color = $bgcolor;
				}
				imagesetpixel( $destimg, $x-$minX, $y-$minY, $color );
			}
		}
		return new Imager( $destimg, $this->getTransparencyInfo() );
	}

	/**
	* Add a watermark image to the base image
	*
	* @param Imager $img the picture want to add
	* @param integer $direction where will be the watermark at the picture
	* @param integer $x X offset direction, when you use ABSOULUTE direction
	* @param integer $y Y offset direction, when you use ABSOULUTE direction
	* @return Imager
	*/
	public function watermark( $img, $direction = Imager::BOTTOM_RIGHT, $x = 0, $y = 0 ) {
		$img_ti = $img->getTransparencyInfo();
		$this_ti = $this->getTransparencyInfo();

		$alpharesize = false;
		if ( $img_ti != null && $img_ti->transparent && $img_ti->alphachannel ) {
			$alpharesize = true;
		}

		$alphaimg = false;
		if ( $this_ti != null && $this_ti->transparent && $this_ti->alphachannel ) {
			$alphaimg = true;
		}
		
		if ( ! ($img instanceof Imager) ) $img = new Imager( $img );
		$t = imagecreatetruecolor( $this->width, $this->height );

		if ( $alpharesize || $alphaimg ) {
			imagealphablending( $t, false );
			imagesavealpha( $t, true );
		}

		imagecopyresampled( $t, $this->imgres, 0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height );

		if ( $alpharesize && $alphaimg ) {
			imagealphablending( $t, true );
		}

		$wmark_width  = $img->width;
		$wmark_height = $img->height;

		switch( $direction ) {
			case Imager::CENTER :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), ( $this->width - $wmark_width ) / 2, ( $this->height - $wmark_height ) / 2, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), ( $this->width - $wmark_width ) / 2, ( $this->height - $wmark_height ) / 2, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
			case Imager::TOP_LEFT :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), 0, 0, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), 0, 0, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
			case Imager::TOP_RIGHT :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), $this->width - $wmark_width, 0, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), $this->width - $wmark_width, 0, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
			case Imager::BOTTOM_LEFT :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), 0, $this->height - $wmark_height, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), 0, $this->height - $wmark_height, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
			case Imager::ABSOLUTE :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), $x, $y, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), $x, $y, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
			default:
			case Imager::BOTTOM_RIGHT :
				if ( $alpharesize ) {
					imagecopy( $t, $img->getResource(), $this->width - $wmark_width, $this->height - $wmark_height, 0, 0, $wmark_width, $wmark_height );
				} else {
					imagecopymerge( $t, $img->getResource(), $this->width - $wmark_width, $this->height - $wmark_height, 0, 0, $wmark_width, $wmark_height, 100 );
				}
				break;
		}
		return new Imager( $t, $this->getTransparencyInfo() );
	}

	/**
	* Add darkness to the picture
	*
	* @param integer $amount Amount of the darkness
	* @return Imager
	*/
	public function darkness( $amount = 40 ) {
		$img = $this->cloneObject( false );
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$rgb = imagecolorat( $this->imgres, $x, $y );
				$alpha = $rgb >> 24;
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				$nr = min( 255, max( $r - $r * $amount / 255, 0 ) );
				$ng = min( 255, max( $g - $g * $amount / 255, 0 ) );
				$nb = min( 255, max( $b - $b * $amount / 255, 0 ) );
				$ncolor = imagecolorallocatealpha( $img->imgres, $nr, $ng, $nb, $alpha );
				imagesetpixel( $img->imgres, $x, $y, $ncolor );
			}
		}
		return $img;
	}

	public function saturation( $amount = 90 ) {
		$img = $this->cloneObject( false );
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$rgb = imagecolorat( $this->imgres, $x, $y );
				$alpha = $rgb >> 24;
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				$gray = ( $r + $g + $b ) / 3 ;
				$nr = min( 255, max( $gray + ( ( $r - $gray ) * $amount / 255 ), 0 ) );
				$ng = min( 255, max( $gray + ( ( $g - $gray ) * $amount / 255 ), 0 ) );
				$nb = min( 255, max( $gray + ( ( $b - $gray ) * $amount / 255 ), 0 ) );
				$ncolor = imagecolorallocatealpha( $img->imgres, $nr, $ng, $nb, $alpha );
				imagesetpixel( $img->imgres, $x, $y, $ncolor );
			}
		}
		return $img;
	}

	/**
	* Add random noise to the picture
	*
	* @return Imager
	*/
	public function noise() {
		$img = $this->cloneObject();
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				if ( rand( 0, 1 ) == 1 ) {
					$rgb = imagecolorat( $this->imgres, $x, $y );
					$alpha = $rgb >> 24;
					$red = ( $rgb >> 16 ) & 0xFF;
					$green = ( $rgb >> 8 ) & 0xFF;
					$blue = $rgb & 0xFF;
					$modifier = rand( -20, 20 );
					$red += $modifier;
					$green += $modifier;
					$blue += $modifier;

					if ( $red > 255 ) $red = 255;
					if ( $green > 255 ) $green = 255;
					if ( $blue > 255 ) $blue = 255;
					if ( $red < 0 ) $red = 0;
					if ( $green < 0 ) $green = 0;
					if ( $blue < 0 ) $blue = 0;

					$ncolor = imagecolorallocatealpha( $img->imgres, $nr, $ng, $nb, $alpha );
					imagesetpixel( $img->imgres, $x, $y, $ncolor );
				}
			}
		}
		return $img;
	}

	public function scatter() {
		$img = $this->cloneObject( true );
		for( $y = 0; $y < $this->height; ++$y ) {
			for( $x = 0; $x < $this->width; ++$x ) {
				$distx = rand( -4, 4 );
				$disty = rand( -4, 4 );

				if ( ( $x + $distx ) >= $this->width ) continue;
				if ( ( $x + $distx ) < 0 ) continue;
				if ( ( $y + $disty ) >= $this->height ) continue;
				if ( ( $y + $disty ) < 0 ) continue;

				$oldcol = imagecolorat( $this->imgres, $x, $y );
				$newcol = imagecolorat( $this->imgres, $x + $distx, $y + $disty );
				imagesetpixel( $img->imgres, $x, $y, $newcol);
				imagesetpixel( $img->imgres, $x + $distx, $y + $disty, $oldcol );
			}
		}
		return $img;
	}

	/**
	* Pixelate the image
	*
	* @param int $blocksize
	* @return Imager
	*/
	public function pixelate( $blocksize = 12 ) {
		$img = $this->cloneObject();
		for( $y = 0; $y < $this->height; $y += $blocksize ) {
			for( $x = 0; $x < $this->width; $x += $blocksize ) {
				$rgb = imagecolorat( $this->imgres, $x, $y );
				imagefilledrectangle( $img->imgres, $x, $y, $x + $blocksize - 1, $y + $blocksize - 1, $rgb );
			}
		}
		return $img;
	}

	/* Some imagefiler effects */
	public function imagefilterhueadjustment( array $newColor ) {
		$img = $this->cloneObject( false );
		$rgb = $newColor[0] + $newColor[1] + $newColor[2];
		$col = array( $newColor[0] / $rgb, $newColor[2] / $rgb, $newColor[1] / $rgb );
		for( $x = 0; $x < $img->width; $x++ ) {
			for( $y = 0; $y < $img->height; $y++ ) {
				$color = imagecolorsforindex( $this->imgres, imagecolorat( $this->imgres, $x, $y ) );
				$newR = $color[ 'red' ] * $col[0] + $color[ 'green' ] * $col[1] + $color[ 'blue' ] * $col[2];
				$newG = $color[ 'red' ] * $col[2] + $color[ 'green' ] * $col[0] + $color[ 'blue' ] * $col[1];
				$newB = $color[ 'red' ] * $col[1] + $color[ 'green' ] * $col[2] + $color[ 'blue' ] * $col[0];
				$ncolor = imagecolorallocatealpha( $img->imgres, $newR, $newG, $newB, $color[ 'alpha' ] );
				imagesetpixel( $img->imgres, $x, $y, $ncolor );
			}
		}
		return $img;
	}

	public function blur() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_SELECTIVE_BLUR );
		return $img;
	}

	public function blurgaussian() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_GAUSSIAN_BLUR );
		return $img;
	}

	public function brightness( $level = 10 ) {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_BRIGHTNESS, $level );
		return $img;
	}

	public function contrast( $level = 10 ) {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_CONTRAST, $level );
		return $img;
	}

	public function gamma( $from = 1.0, $to = 1.537 ) {
		$img = $this->cloneObject();
		imagegammacorrect( $img->imgres, $from, $to );
		return $img;
	}

	public function grayscale() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_GRAYSCALE );
		return $img;
	}

	public function negate() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_NEGATE );
		return $img;
	}

	public function sepia() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_GRAYSCALE );
		imagefilter( $img->imgres, IMG_FILTER_COLORIZE, 112, 66, 20 );
		return $img;
	}

	public function sketch() {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_MEAN_REMOVAL );
		return $img;
	}

	public function smooth( $level = 10 ) {
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_SMOOTH, $level );
		return $img;
	}
	
	public function multiplyColor( $color = array( 255, 0, 0 ) ) {
		$img = $this->cloneObject();
		//get opposite color
		$opposite = array( 255 - $color[ 0 ], 255 - $color[ 1 ], 255 - $color[ 2 ] );
		//now we subtract the opposite color from the image
		imagefilter( $img->imgres, IMG_FILTER_COLORIZE, -$opposite[ 0 ], -$opposite[ 1 ], -$opposite[ 2 ] );
		return $img;
	}

	public function colorScale(array $color){
		$img = $this->cloneObject();
		imagefilter( $img->imgres, IMG_FILTER_GRAYSCALE );
		$luminance = ( $color[ 0 ] + $color[ 1 ] + $color[ 2 ] ) / 3; // average luminance added by the color
		$brightnessCorrection = $luminance / 3; // quantity of brightness to correct for each channel
		if( $luminance < 127 ){
		    $brightnessCorrection -= 127 / 3; // color is dark so we have to negate the brightness correction
		}
		imagefilter( $img->imgres, IMG_FILTER_COLORIZE, $color[ 0 ] - $luminance, $color[ 1 ] - $luminance, $color[ 2 ] - $luminance );
		imagefilter( $img->imgres, IMG_FILTER_BRIGHTNESS, $brightnessCorrection );
		return $img;
	}

	public function threshold( $val ) {
		$img = $this->cloneObject();
		$gray = $this->grayscale();
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$color = imagecolorsforindex( $img->imgres, imagecolorat( $img->imgres, $x, $y ) );
				$b = ( $color[ 'blue' ] < $val ) ? 0 : 255;
				$ncolor = imagecolorallocatealpha( $img->imgres, $b, $b, $b, $color[ 'alpha' ] );
				imagesetpixel( $img->imgres, $x, $y, $ncolor );
			}
		}
		return $img;
	}

	public function autothreshold() {
		/* ISOData algorithm to detect the optimal threshold value */
		$freq = array_fill( 0, 255, 0 );
		$img = $this->cloneObject();
		$gray = $this->grayscale();
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$color = imagecolorsforindex( $img->imgres, imagecolorat( $img->imgres, $x, $y ) );
				$freq[ $color[ 'blue' ] ]++;
			}
		}
		/* ISOData alg. */
		$tprev = $nu = $jk = $bk = 0;
		$T = 128;
		$pixelnum = $this->width * $this->height;
		foreach( $freq as $i => $val ) {
			$nu += (double)( $val * $i ) / $pixelnum;
		}
		while( $tprev != $T ) {
			$bt = $nut = 0;
			for ( $i = 0; $i <= $T; $i++ ) {
				$bt += (double)( $freq[ $i ] ) / $pixelnum;
				$nut += (double)( $freq[ $i ] * $i ) / $pixelnum;
			}
			$jk = $nut / $bt;
			$bk = (double)( $nu - $nut ) / ( 1 - $bt );
			$tprev = $T;
			$T = floor( (double)( $jk + $bk ) / 2 );
		}
		return $img->threshold( $T );
	}

	public function blurgaussian2() {
		$matrix = array(
			array(  1,  2,  1 ),
			array(  2,  4,  2 ),
			array(  1,  2,  1 ) );
		$divisor = 16;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function emboss() {
		$matrix = array(
			array(  2,  0,  0 ),
			array(  0, -1,  0 ),
			array(  0,  0, -1 ) );
		$divisor = 1;
		$offset = 127;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function embosssoft() {
		$matrix = array(
			array(  1,  0,  0 ),
			array(  0,  0,  0 ),
			array(  0,  0, -1 ) );
		$divisor = 1;
		$offset = 127;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeTopDown() {
		$matrix = array(
			array(  1,  1,  1 ),
			array(  1, -2,  1 ),
			array( -1, -1, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeHorizontal() {
		$matrix = array(
			array( -1, -1, -1 ),
			array(  2,  2,  2 ),
			array( -1, -1, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeVertical() {
		$matrix = array(
			array( -1,  2, -1 ),
			array( -1,  2, -1 ),
			array( -1,  2, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeLeftDiagonal() {
		$matrix = array(
			array(  2, -1, -1 ),
			array( -1,  2, -1 ),
			array( -1, -1,  2 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeRightDiagonal() {
		$matrix = array(
			array( -1, -1,  2 ),
			array( -1,  2, -1 ),
			array(  2, -1, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function edgeEnhance() {
		$matrix = array(
			array(  0, -1,  0 ),
			array( -1,  5, -1 ),
			array(  0, -1,  0 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function hipass() {
		$matrix = array(
			array( -1, -1, -1 ),
			array( -1,  9, -1 ),
			array( -1, -1, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function laplace() {
		$matrix = array(
			array( -1, -1, -1 ),
			array( -1,  8, -1 ),
			array( -1, -1, -1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}
	
	public function sharpen() {
		$matrix = array(
			array( -1, -1, -1 ),
			array( -1, 16, -1 ),
			array( -1, -1, -1 ) );
		$divisor = 8;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function soften() {
		$matrix = array(
			array(  2,  2,  2 ),
			array(  2,  0,  2 ),
			array(  2,  2,  2 ) );
		$divisor = 16;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function softenLess() {
		$matrix = array(
			array(  0,  1,  0 ),
			array(  1,  2,  1 ),
			array(  0,  1,  0 ) );
		$divisor = 6;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}
	
	public function sobelhorizontal() {
		$matrix = array(
			array( -1, -2, -1 ),
			array(  0,  0,  0 ),
			array(  1,  2,  1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function sobelvertical() {
		$matrix = array(
			array( -1,  0,  1 ),
			array( -2,  0,  2 ),
			array( -1,  0,  1 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function arithmeticmean() {
		$matrix = array(
			array( 1/9, 1/9, 1/9 ),
			array( 1/9, 1/9, 1/9 ),
			array( 1/9, 1/9, 1/9 ) );
		$divisor = 1;
		$offset = 0;
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function convolution( array $matrix, $divisor, $offset ) {
		$img = $this->cloneObject();
		imageconvolution( $img->imgres, $matrix, $divisor, $offset );
		return $img;
	}

	public function toPalette( $maxNumColor, $dither = false ) {
		$img = $this->cloneObject();
		imagetruecolortopalette( $img->imgres, $dither, $maxNumColor );
		return $img;
	}

	/**
	* Get the image component values 
	*
	* @param $channel : 0 : RGB, 1 : red, 2 : green, 3 : blue, 4 : alpha ...
	* @return array 
	*/
	public function getChannel( $channel = Channel::RGB ) {
		$ret = array();
		for( $y = 0; $y < $this->height; $y++ ) {
			for( $x = 0; $x < $this->width; $x++ ) {
				$color = imagecolorsforindex( $this->imgres, imagecolorat( $this->imgres, $x, $y ) );
				switch( $channel ) {
					case Channel::RGB :
						$ret[ $y ][ $x ] = $color;
						break;
					case Channel::RED :
						$ret[ $y ][ $x ] = $color[ 'red' ];
						break;
					case Channel::GREEN :
						$ret[ $y ][ $x ] = $color[ 'green' ];
						break;
					case Channel::BLUE :
						$ret[ $y ][ $x ] = $color[ 'blue' ];
						break;
					case Channel::ALPHA :
						$ret[ $y ][ $x ] = $color[ 'alpha' ];
						break;
					case Channel::HSL :
						unset( $color[ 'alpha' ] );
						$ret[ $y ][ $x ] = ( max( $color ) + min( $color ) ) / 2;
						break;
					case Channel::HSV :
						unset( $color[ 'alpha' ] );
						$ret[ $y ][ $x ] = max( $color );
						break;
					case Channel::HSI :
						unset( $color[ 'alpha' ] );
						$ret[ $y ][ $x ] = array_sum( $color ) / 3;
						break;
					case Channel::HSL2 :
						$ret[ $y ][ $x ] = ( 3 * $color[ 'red' ] + 6 * $color[ 'green' ] + $color[ 'blue' ] ) / 10;
						break;
					case Channel::HSP :
						$ret[ $y ][ $x ] = round( sqrt( 0.299 * $color[ 'red' ] * $color[ 'red' ]
							+ 0.587 * $color[ 'green' ] * $color[ 'green' ]
							+ 0.114 * $color[ 'blue' ] * $color[ 'blue' ] ) );
						break;
					case Channel::CCIR601 :
						$ret[ $y ][ $x ] = (int)( 0.299 * $color[ 'red' ] + 0.587 * $color[ 'green' ] + 0.114 * $color[ 'blue' ] );
						break;
					case Channel::ITU_R :
						$ret[ $y ][ $x ] = (int)( 0.2126 * $color[ 'red' ] + 0.7152 * $color[ 'green' ] + 0.0722 * $color[ 'blue' ] );
						break;
				}				
			}
		}
		return $ret;
	}
	
	/**
	* Get the image channel component statistic
	*
	* @param $channel
	* @return Imager
	*/
	public function getHistogramData( $channel = Channel::RED ) {
		if ( $channel == Channel::RGB ) { throw new Exception( 'only one component value supported' ); }
		$stats = array_fill( 0, 256, 0 );
		$channels = $this->getChannel( $channel );
		foreach( $channels as $row ) {
			foreach( $row as $pixel ) {
				$stats[ $pixel ]++;
			}
		}
		return $stats;
	}
	
	/**
	* Make an alphablended channel histogram with the given size and color.
	*
	* @param int $channel
	* @param int $width
	* @param int $height
	* @param array $color
	* @param int $lineHeight
	* @return Imager
	*/	
	public function makeHistogram( $channel = Channel::RED, $width, $height, array $color = array( 133, 90, 130 ), $lineHeight = 1 ) {
		$img = new Imager( new Canvas( $width, $height, array( 255, 255, 255 ) ), new Transparency( array( 255, 255, 255 ) ) );
		$img = $img->transpColorToAlphaChannel();
		$colors = $this->getHistogramData( $channel );
		$min = min( $colors );
		$max = max( $colors );
		$ratio_h = $height / $max;
		$ratio_w = $width / 256;
		foreach( $colors as $k => $value ) {
			$x = round( $k * $ratio_w );
			$img = $img->drawLine( $x, $height, $x, $height - ( $value * $ratio_h ), $color, $lineHeight );
		}
		return $img;
	}
	
	/**
	* Change the transparency (alpha value) with the given value.
	* You can use negativ or positive value to increment or decrement the alpha value
	*
	* @param int $value 
	* @return Imager $img
	*/
	public function transparency( $value = 0 ) {
		$img = $this->cloneObject();
		$img->setTransparencyInfo( new Transparency( true ) );
		imagealphablending( $img->imgres, false );
		for( $x = 0; $x < $this->width; $x++ ) {
			for( $y = 0; $y < $this->height; $y++ ) {
				$pixel = imagecolorsforindex( $img->imgres, imagecolorat( $img->imgres, $x, $y ) );
				$alpha = min( max( 0, $pixel[ 'alpha' ] + $value ), 127 );
				$color = imagecolorallocatealpha( $img->imgres, $pixel[ 'red' ], $pixel[ 'green' ], $pixel[ 'blue' ], $alpha );
				imagesetpixel( $img->imgres, $x, $y, $color );
			}
		}
		return $img;
	}
	
	/**
	* Make Image from array values. The array format array[ x ][ y ] = value
	* or array[ x ][ y ] = array( 'red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0 )
	*
	* @param array $pixels
	* @return Imager
	*/	
	public function createFromArray( array $pixels ) {
		$height = count( $pixels );
		$width = count( $pixels[ 0 ] );
		$img = new Imager( new Canvas( $width, $height ) );
		$res = $img->getResource();
		foreach( $pixels as $x => $row ) {
			foreach( $row as $y => $pixel ) {
				$r = $g = $b = 0;
				$color = null;
				if ( !is_array( $pixel ) ) {
					$r = $g = $b = $pixel;
					$color = imagecolorallocate( $res, $r, $g, $b );
				} else {
					$color = imagecolorallocatealpha( $t, $pixel[ 'red' ], $pixel[ 'green' ], $pixel[ 'blue' ], $pixel[ 'alpha' ] );
				}				
				imagesetpixel( $res, $y, $x, $color );
			}
		}
		return $img;
	}
}
?>