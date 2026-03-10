<?php
/**
 * Generate Barcode Image
 * Outputs a barcode image for a given code
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration
require_once $root_path . '/config.php';

// Include the barcode library (make sure it's installed via composer)
require_once $root_path . '/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

// Get barcode code from URL
$code = isset($_GET['code']) ? $_GET['code'] : '';
$width = isset($_GET['width']) ? (int)$_GET['width'] : 300;
$height = isset($_GET['height']) ? (int)$_GET['height'] : 60;

if (empty($code)) {
    // Return a placeholder or error image
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
    header('Content-Disposition: inline; filename="barcode.png"');
    echo $barcode;
    
} catch (Exception $e) {
    // Return error image
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(200, 60);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text_color = imagecolorallocate($im, 255, 0, 0);
    imagefilledrectangle($im, 0, 0, 200, 60, $bg);
    imagestring($im, 3, 10, 20, 'Error: ' . substr($e->getMessage(), 0, 20), $text_color);
    imagepng($im);
    imagedestroy($im);
}
?>
