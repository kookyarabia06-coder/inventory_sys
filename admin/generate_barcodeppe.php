<?php
/**
 * Generate Barcode for PPE Equipment
 * Specialized barcode generator for PPE items
 */

// Turn off error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration
if (file_exists($root_path . '/config.php')) {
    require_once $root_path . '/config.php';
}

// Check and load autoload
$autoload_paths = [
    $root_path . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    $root_path . '/includes/vendor/autoload.php'
];

$autoload_loaded = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_loaded = true;
        break;
    }
}

if (!$autoload_loaded) {
    // If autoload not found, output a text fallback
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Barcode Generator - Setup Required</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
            .error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; color: #721c24; }
            .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; color: #155724; }
            code { background: #f4f4f4; padding: 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2><i class="fas fa-exclamation-triangle"></i> Barcode Generator Setup Required</h2>
            <p>The barcode library is not installed. Please run the following command in your terminal:</p>
            <code>composer require picqer/php-barcode-generator</code>
            
            <h3 style="margin-top: 20px;">Quick Setup Instructions:</h3>
            <ol>
                <li>Open Command Prompt / Terminal</li>
                <li>Navigate to your project root: <code>cd <?php echo $root_path; ?></code></li>
                <li>Run: <code>composer require picqer/php-barcode-generator</code></li>
            </ol>
            
            <p style="margin-top: 20px;">After installation, refresh this page.</p>
        </div>
        
        <div class="success-box" style="margin-top: 20px; display: none;" id="textFallback">
            <h3>Text Barcode Fallback</h3>
            <p>For now, you can use this text representation:</p>
            <p style="font-family: monospace; font-size: 24px; letter-spacing: 5px;" id="barcodeText"></p>
        </div>
        
        <script>
            // Get barcode value from URL
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            if (code) {
                document.getElementById('textFallback').style.display = 'block';
                document.getElementById('barcodeText').textContent = code;
                document.title = 'Barcode: ' + code;
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;

// Check if GD extension is loaded
$gd_loaded = extension_loaded('gd');

// Get parameters
$code = isset($_GET['code']) ? $_GET['code'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'png'; // png, html, or text
$width = isset($_GET['width']) ? intval($_GET['width']) : 300;
$height = isset($_GET['height']) ? intval($_GET['height']) : 80;
$factor = isset($_GET['factor']) ? intval($_GET['factor']) : 2;

// Validate barcode code
if (empty($code)) {
    if ($format == 'png') {
        header('HTTP/1.0 400 Bad Request');
        header('Content-Type: text/plain');
        die('Error: Barcode code is required');
    } else {
        echo '<div style="color: red; padding: 20px;">Error: Barcode code is required</div>';
        exit;
    }
}

// Validate barcode format (allow PPE specific prefixes)
if (!preg_match('/^[A-Za-z0-9\-_]+$/', $code)) {
    if ($format == 'png') {
        header('HTTP/1.0 400 Bad Request');
        header('Content-Type: text/plain');
        die('Error: Invalid barcode format. Use only letters, numbers, hyphens and underscores');
    } else {
        echo '<div style="color: red; padding: 20px;">Error: Invalid barcode format</div>';
        exit;
    }
}

try {
    $generator = new BarcodeGeneratorPNG();
    
    // Handle different output formats
    switch ($format) {
        case 'html':
            // Generate HTML barcode (good for fallback when GD is missing)
            $htmlGenerator = new BarcodeGeneratorHTML();
            $barcodeHtml = $htmlGenerator->getBarcode($code, $htmlGenerator::TYPE_CODE_128);
            
            header('Content-Type: text/html');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>PPE Barcode - <?php echo htmlspecialchars($code); ?></title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 0; 
                        padding: 20px; 
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        background: #f5f5f5;
                    }
                    .barcode-container {
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .barcode-wrapper {
                        margin: 20px 0;
                    }
                    .barcode-value {
                        font-family: monospace;
                        font-size: 18px;
                        color: #333;
                        margin-top: 15px;
                        padding-top: 15px;
                        border-top: 1px dashed #ccc;
                    }
                    .ppe-label {
                        background: #F16D34;
                        color: white;
                        padding: 5px 15px;
                        border-radius: 20px;
                        display: inline-block;
                        font-size: 14px;
                        margin-bottom: 15px;
                    }
                    @media print {
                        body { background: white; }
                        .barcode-container { box-shadow: none; }
                    }
                </style>
            </head>
            <body>
                <div class="barcode-container">
                    <div class="ppe-label">PPE Equipment</div>
                    <div class="barcode-wrapper">
                        <?php echo $barcodeHtml; ?>
                    </div>
                    <div class="barcode-value"><?php echo htmlspecialchars($code); ?></div>
                </div>
                <script>
                    // Auto-print if requested
                    if (window.location.search.includes('print=1')) {
                        window.onload = function() { setTimeout(function() { window.print(); }, 500); }
                    }
                </script>
            </body>
            </html>
            <?php
            break;
            
        case 'text':
            // Text-only representation (simplest fallback)
            header('Content-Type: text/html');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>PPE Barcode - <?php echo htmlspecialchars($code); ?></title>
                <style>
                    body { 
                        font-family: 'Courier New', monospace; 
                        margin: 0; 
                        padding: 20px;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        background: #f5f5f5;
                    }
                    .text-barcode {
                        background: white;
                        padding: 40px;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .barcode-letters {
                        font-size: 32px;
                        letter-spacing: 8px;
                        font-weight: bold;
                        color: #000;
                        margin: 20px 0;
                    }
                    .barcode-value {
                        font-size: 18px;
                        color: #666;
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #ccc;
                    }
                    .ppe-label {
                        background: #F16D34;
                        color: white;
                        padding: 5px 15px;
                        border-radius: 20px;
                        display: inline-block;
                        font-size: 14px;
                        margin-bottom: 15px;
                    }
                </style>
            </head>
            <body>
                <div class="text-barcode">
                    <div class="ppe-label">PPE Equipment</div>
                    <div class="barcode-letters">
                        <?php 
                        // Create a simple visual representation
                        for($i = 0; $i < strlen($code); $i++) {
                            echo '|';
                        }
                        ?>
                    </div>
                    <div class="barcode-value"><?php echo htmlspecialchars($code); ?></div>
                </div>
            </body>
            </html>
            <?php
            break;
            
        case 'png':
        default:
            // Check if GD is loaded for PNG generation
            if (!$gd_loaded) {
                // Fallback to HTML format if GD not available
                header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($code) . '&format=html&width=' . $width . '&height=' . $height);
                exit;
            }
            
            // Generate PNG barcode
            $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, $factor, 50);
            
            // Set headers for image output
            header('Content-Type: image/png');
            header('Content-Disposition: inline; filename="ppe_barcode_' . $code . '.png"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            header('Content-Length: ' . strlen($barcode));
            
            echo $barcode;
            break;
    }
    
} catch (Exception $e) {
    // Log error and show friendly message
    error_log("PPE Barcode generation error: " . $e->getMessage());
    
    if ($format == 'png') {
        header('Content-Type: text/html');
    }
    
    echo '<div style="color: red; padding: 20px; font-family: Arial, sans-serif;">';
    echo '<h3>Barcode Generation Error</h3>';
    echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Barcode value: ' . htmlspecialchars($code) . '</p>';
    echo '<p style="margin-top: 20px;"><a href="?code=' . urlencode($code) . '&format=text">View as text</a> | ';
    echo '<a href="?code=' . urlencode($code) . '&format=html">View as HTML</a></p>';
    echo '</div>';
}
?>