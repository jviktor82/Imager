# Imager
PHP Image manipulation class

Sample code snippets:

#1.

include 'imager.php';

$q = new Imager( new PNG( "assets/aqr.png" ), new Transparency( true ) );
$qr = new Imager( new PNG( "url_qrcode.png" ) );
$q->addImage( $qr, ( $q->width - $qr->width ) / 2, 220 )->save( new PNG( "4.png" ) );

#2.

include 'imager.php';
$full_width = 100;
$img_inner = new Imager( new PNG( '../assets/pic_inner.png' ), new Transparency( true ) );
$img_inner_new = $img_inner->resize( $full_width, 30 );

$img_left = new Imager( new PNG( '../assets/pic_left.png' ), new Transparency( true ) );
$img_right = new Imager( new PNG( '../assets/pic_right.png' ), new Transparency( true ) );

$img_inner_new = $img_inner_new->addImage( $img_left, 0, 0 );
$img_inner_new = $img_inner_new->addImage( $img_right, $img_inner_new->width - $img_right->width , 0 );
$img_inner_new->save( new PNG( 'transp.png' ) );
