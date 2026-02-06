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

// Simple PDF generator - embeds JPG image in PDF
function createSimplePDF($imagePath, $studentName) {
    // Get image dimensions
    list($width, $height) = getimagesize($imagePath);
    
    // Convert to PDF points (72 points per inch, assuming 96 DPI)
    $pdfWidth = ($width * 72) / 96;
    $pdfHeight = ($height * 72) / 96;
    
    // Read image data
    $imageData = file_get_contents($imagePath);
    $imageBase64 = base64_encode($imageData);
    
    // Create simple PDF structure
    $pdf = "%PDF-1.4\n";
    
    // Object 1: Catalog
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Object 2: Pages
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Object 3: Page
    $pdf .= "3 0 obj\n";
    $pdf .= "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pdfWidth} {$pdfHeight}] ";
    $pdf .= "/Contents 4 0 R /Resources << /XObject << /Im1 5 0 R >> >> >>\n";
    $pdf .= "endobj\n";
    
    // Object 4: Content stream
    $content = "q\n{$pdfWidth} 0 0 {$pdfHeight} 0 0 cm\n/Im1 Do\nQ\n";
    $contentLength = strlen($content);
    $pdf .= "4 0 obj\n<< /Length {$contentLength} >>\nstream\n{$content}endstream\nendobj\n";
    
    // Object 5: Image
    $pdf .= "5 0 obj\n";
    $pdf .= "<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} ";
    $pdf .= "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode ";
    $pdf .= "/Length " . strlen($imageData) . " >>\n";
    $pdf .= "stream\n";
    $pdf .= $imageData;
    $pdf .= "\nendstream\nendobj\n";
    
    // Cross-reference table
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    
    // Calculate positions (simplified - in real PDF these need to be exact)
    $positions = [
        str_pad(strpos($pdf, "1 0 obj"), 10, "0", STR_PAD_LEFT),
        str_pad(strpos($pdf, "2 0 obj"), 10, "0", STR_PAD_LEFT),
        str_pad(strpos($pdf, "3 0 obj"), 10, "0", STR_PAD_LEFT),
        str_pad(strpos($pdf, "4 0 obj"), 10, "0", STR_PAD_LEFT),
        str_pad(strpos($pdf, "5 0 obj"), 10, "0", STR_PAD_LEFT),
    ];
    
    foreach ($positions as $pos) {
        $pdf .= $pos . " 00000 n \n";
    }
    
    // Trailer
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";
    
    return $pdf;
}

try {
    // Check if this is a download request (GET with parameters)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
        // Get parameters from URL
        $studentName = isset($_GET['studentName']) ? trim($_GET['studentName']) : '';
        $courseName = isset($_GET['courseName']) ? trim($_GET['courseName']) : '';
        $instructorName = isset($_GET['instructorName']) ? trim($_GET['instructorName']) : '';
        $date = isset($_GET['date']) ? trim($_GET['date']) : '';
        $language = isset($_GET['language']) ? trim($_GET['language']) : 'en';
        $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'jpg';
        
        // Validate
        if (empty($studentName) || empty($courseName) || empty($instructorName) || empty($date)) {
            sendError('Missing required parameters', 400);
        }
        
        if (!in_array($language, ['ar', 'en'])) {
            $language = 'en';
        }
        
        if (!in_array($format, ['jpg', 'pdf'])) {
            $format = 'jpg';
        }
        
        // Generate certificate and return as downloadable image
        $isDownload = true;
        $downloadFormat = $format;
    } else {
        // Regular POST request
        $isDownload = false;
        $downloadFormat = 'jpg';
        
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
    }

    // Check GD
    if (!extension_loaded('gd')) {
        sendError('GD Library not installed', 500);
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

    // Generate image data
    ob_start();
    imagejpeg($image, null, $config['image']['quality']);
    $imageData = ob_get_clean();
    
    // Clean up (imagedestroy is deprecated in PHP 8.5, but we'll suppress it)
    @imagedestroy($image);
    unset($image);

    // Clear any buffered errors
    ob_clean();

    // If download request, output file directly
    if ($isDownload) {
        // Create filename: StudentName_UniSkills_Certificate.ext
        // Remove special characters but keep Arabic
        $cleanName = preg_replace('/[^a-zA-Z0-9\s\x{0600}-\x{06FF}]/u', '', $studentName);
        $cleanName = preg_replace('/\s+/', '_', $cleanName);
        
        // Create filename with app name
        $baseFilename = $cleanName . '_UniSkills_Certificate';
        
        // For ASCII-safe fallback
        $asciiFilename = 'UniSkills_Certificate';
        
        if ($downloadFormat === 'pdf') {
            // Generate PDF
            // Save image temporarily
            $tempImagePath = sys_get_temp_dir() . '/cert_' . uniqid() . '.jpg';
            file_put_contents($tempImagePath, $imageData);
            
            // Create PDF with FPDF-like approach (simple implementation)
            // Since FPDF might not be available, we'll use a simple approach
            // Convert JPG to PDF using basic PDF structure
            $pdfData = createSimplePDF($tempImagePath, $studentName);
            
            // Clean up temp file
            @unlink($tempImagePath);
            
            // Use RFC 5987 encoding for UTF-8 filenames
            $encodedFilename = rawurlencode($baseFilename . '.pdf');
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $asciiFilename . '.pdf"; filename*=UTF-8\'\'' . $encodedFilename);
            header('Content-Length: ' . strlen($pdfData));
            echo $pdfData;
        } else {
            // Output JPG
            $encodedFilename = rawurlencode($baseFilename . '.jpg');
            
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . $asciiFilename . '.jpg"; filename*=UTF-8\'\'' . $encodedFilename);
            header('Content-Length: ' . strlen($imageData));
            echo $imageData;
        }
        ob_end_flush();
        exit();
    }

    // Otherwise return JSON with base64
    $base64 = base64_encode($imageData);
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
