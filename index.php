<?php
/**
 * Created by PhpStorm.
 * User: EDLENKO
 * Date: 28.05.2015
 * Time: 9:24
 */

require_once 'watermark.class.php';

$watermarkImages = new Watermark('watermark_black.png', 'watermark_white.png');
$imagesNum = $watermarkImages->run();

header('Content-type: text/html');

echo 'FINISHED!';
echo '<br/>';
echo $imagesNum.' images successfully watermarked.';
