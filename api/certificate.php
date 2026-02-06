<?php
// Start output buffering to catch any errors
ob_start();

// Disable error display (only log errors)
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

// Clean any previous output
ob_clean();

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load dependencies
require_once __DIR__ . '/../arabic_glyphs.php';

// Load config
$config = require __DIR__ . '/../config.php';

// Error handler
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

try {
    // Check GD
    if (!extension_loaded('gd')) {
        sendError('GD Library not installed', 500);
    }

    // Get request data
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    // Validate required fields
    $required = ['studentName', 'courseName', 'instructorName', 'date', 'language'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            sendError("Missing field: $field", 400);
        }
    }

    $studentName = trim($data['studentName']);
    $courseName = trim($data['courseName']);
    $instructorName = trim($data['instructorName']);
    $date = trim($data['date']);
    $language = trim($data['language']);

    if (!in_array($language, ['ar', 'en'])) {
        sendError('Invalid language. Must be "ar" or "en"', 400);
    }

    // Load template
    $templatePath = __DIR__ . "/../templates/{$language}.jpg";
    if (!file_exists($templatePath)) {
        sendError("Template not found: {$language}.jpg", 404);
    }

    $image = @imagecreatefromjpeg($templatePath);
    if (!$image) {
        sendError('Failed to load template', 500);
    }

    // Setup
    $white = imagecolorallocate($image, 255, 255, 255);
    $fontPath = __DIR__ . '/../fonts/Cairo-Bold.ttf';
    if (!file_exists($fontPath)) {
        $fontPath = null;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $scale = $width / $config['image']['base_width'];
    $isArabic = ($language === 'ar');

    // Get language-specific positions
    $positions = $isArabic ? 
        (isset($config['positions_ar']) ? $config['positions_ar'] : $config['positions']) : 
        (isset($config['positions_en']) ? $config['positions_en'] : $config['positions']);

    // Helper functions
    function writeCenteredText($image, $fontSize, $y, $text, $color, $fontPath, $width, $isArabic) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        if ($isArabic) {
            $text = ArabicGlyphs::utf8Glyphs($text);
        }
        
        if ($fontPath && file_exists($fontPath)) {
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
            if ($bbox) {
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = ($width - $textWidth) / 2;
                @imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
                return true;
            }
        }
        return false;
    }

    function writeText($image, $fontSize, $x, $y, $text, $color, $fontPath, $isArabic) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        if ($isArabic) {
            $text = ArabicGlyphs::utf8Glyphs($text);
        }
        
        if ($fontPath && file_exists($fontPath)) {
            @imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
            return true;
        }
        return false;
    }

    // Add text to certificate
    writeCenteredText(
        $image, 
        $positions['student_name']['font_size'] * $scale, 
        $positions['student_name']['y'] * $scale, 
        $studentName, 
        $white, 
        $fontPath, 
        $width, 
        $isArabic
    );

    writeCenteredText(
        $image, 
        $positions['course_name']['font_size'] * $scale, 
        $positions['course_name']['y'] * $scale, 
        $courseName, 
        $white, 
        $fontPath, 
        $width, 
        $isArabic
    );

    writeText(
        $image, 
        $positions['date']['font_size'] * $scale, 
        $positions['date']['x'] * $scale, 
        $height - ($positions['date']['y_from_bottom'] * $scale), 
        $date, 
        $white, 
        $fontPath, 
        $isArabic
    );

    // Instructor name
    $instructorFontSize = $positions['instructor']['font_size'] * $scale;
    $instructorY = $height - ($positions['instructor']['y_from_bottom'] * $scale);
    
    if ($fontPath && file_exists($fontPath)) {
        $instructorText = $isArabic ? ArabicGlyphs::utf8Glyphs($instructorName) : $instructorName;
        $bbox = @imagettfbbox($instructorFontSize, 0, $fontPath, $instructorText);
        $textWidth = $bbox ? abs($bbox[4] - $bbox[0]) : 100;
    } else {
        $textWidth = 100;
    }
    
    $instructorX = $width - ($positions['instructor']['x_from_right'] * $scale) - $textWidth;
    writeText(
        $image, 
        $instructorFontSize, 
        $instructorX, 
        $instructorY, 
        $instructorName, 
        $white, 
        $fontPath, 
        $isArabic
    );

    // Output image as base64
    ob_start();
    imagejpeg($image, null, $config['image']['quality']);
    $imageData = ob_get_clean();
    $base64 = base64_encode($imageData);
    
    // Clean up (imagedestroy is deprecated in PHP 8.5, but we'll suppress it)
    @imagedestroy($image);
    unset($image);

    // Clear any buffered errors
    ob_clean();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'image' => 'data:image/jpeg;base64,' . $base64
    ]);
    
    // End output buffering
    ob_end_flush();

} catch (Exception $e) {
    // Clean up if error
    if (isset($image) && $image) {
        @imagedestroy($image);
        unset($image);
    }
    
    // Clear buffer
    ob_clean();
    
    sendError($e->getMessage(), 500);
}
?>
