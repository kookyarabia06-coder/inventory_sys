<?php
/**
 * Barcode Generator for Issued Items
 * Outputs a barcode image for a given code
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load autoloader
require_once $root_path . '/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

// Get barcode code from URL
$code = isset($_GET['code']) ? $_GET['code'] : (isset($_GET['barcode_value']) ? $_GET['barcode_value'] : '');
$width = isset($_GET['width']) ? (int)$_GET['width'] : 300;
$height = isset($_GET['height']) ? (int)$_GET['height'] : 60;

if (empty($code)) {
    // Return a simple error image
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(200, 60);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text_color = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, 200, 60, $bg);
    imagestring($im, 3, 10, 20, 'No barcode data', $text_color);
    imagepng($im);
    imagedestroy($im);
    exit;
}

try {
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, $height);
    
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $barcode;
    
} catch (Exception $e) {
    // Return error image
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(200, 60);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text_color = imagecolorallocate($im, 255, 0, 0);
    imagefilledrectangle($im, 0, 0, 200, 60, $bg);
    imagestring($im, 3, 10, 20, 'Error: ' . substr($e->getMessage(), 0, 15), $text_color);
    imagepng($im);
    imagedestroy($im);
}
?>