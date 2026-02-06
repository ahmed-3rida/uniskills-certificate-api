<?php
/**
 * Arabic Text Shaping for GD
 * Handles Arabic text with proper shaping and mixed Arabic/English text
 */

class ArabicGlyphs {
    
    /**
     * Convert Arabic text to display glyphs
     * Handles mixed Arabic/English text properly with spaces
     * NO REVERSAL - text flows naturally like English
     */
    public static function utf8Glyphs($text) {
        // If no Arabic, return as-is
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }
        
        // Split text into segments (Arabic vs non-Arabic vs spaces)
        $segments = self::splitTextSegments($text);
        
        // Process each segment
        $processedSegments = [];
        foreach ($segments as $segment) {
            if ($segment['type'] === 'arabic') {
                $processedSegments[] = self::shapeArabicText($segment['text'], false); // Don't reverse
            } elseif ($segment['type'] === 'space') {
                // Keep space as-is
                $processedSegments[] = ' ';
            } else {
                // Keep non-Arabic as-is
                $processedSegments[] = $segment['text'];
            }
        }
        
        // DON'T reverse segments - keep natural flow like English
        return implode('', $processedSegments);
    }
    
    /**
     * Split text into Arabic and non-Arabic segments
     * Preserves spaces properly
     */
    private static function splitTextSegments($text) {
        $segments = [];
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $currentSegment = '';
        $currentType = null;
        
        foreach ($chars as $char) {
            // Treat space as separate segment to preserve it
            if ($char === ' ') {
                // Save current segment if exists
                if ($currentSegment !== '') {
                    $segments[] = [
                        'type' => $currentType,
                        'text' => $currentSegment
                    ];
                    $currentSegment = '';
                    $currentType = null;
                }
                // Add space as its own segment
                $segments[] = [
                    'type' => 'space',
                    'text' => ' '
                ];
                continue;
            }
            
            $isArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $char);
            $type = $isArabic ? 'arabic' : 'other';
            
            if ($currentType === null) {
                $currentType = $type;
                $currentSegment = $char;
            } elseif ($currentType === $type) {
                $currentSegment .= $char;
            } else {
                // Type changed, save current segment
                $segments[] = [
                    'type' => $currentType,
                    'text' => $currentSegment
                ];
                $currentType = $type;
                $currentSegment = $char;
            }
        }
        
        // Add last segment
        if ($currentSegment !== '') {
            $segments[] = [
                'type' => $currentType,
                'text' => $currentSegment
            ];
        }
        
        return $segments;
    }
    
    /**
     * Shape Arabic text (convert to proper glyphs)
     * @param string $text The Arabic text to shape
     * @param bool $reverse Whether to reverse the text (default: false)
     */
    private static function shapeArabicText($text, $reverse = false) {
        // Arabic character forms
        $arabicChars = [
            // Letter => [isolated, final, initial, medial]
            'ا' => ['ا', 'ﺎ', 'ﺍ', 'ﺎ'],
            'أ' => ['أ', 'ﺄ', 'ﺃ', 'ﺄ'],
            'إ' => ['إ', 'ﺈ', 'ﺇ', 'ﺈ'],
            'آ' => ['آ', 'ﺂ', 'ﺁ', 'ﺂ'],
            'ب' => ['ب', 'ﺐ', 'ﺑ', 'ﺒ'],
            'ت' => ['ت', 'ﺖ', 'ﺗ', 'ﺘ'],
            'ث' => ['ث', 'ﺚ', 'ﺛ', 'ﺜ'],
            'ج' => ['ج', 'ﺞ', 'ﺟ', 'ﺠ'],
            'ح' => ['ح', 'ﺢ', 'ﺣ', 'ﺤ'],
            'خ' => ['خ', 'ﺦ', 'ﺧ', 'ﺨ'],
            'د' => ['د', 'ﺪ', 'ﺩ', 'ﺪ'],
            'ذ' => ['ذ', 'ﺬ', 'ﺫ', 'ﺬ'],
            'ر' => ['ر', 'ﺮ', 'ﺭ', 'ﺮ'],
            'ز' => ['ز', 'ﺰ', 'ﺯ', 'ﺰ'],
            'س' => ['س', 'ﺲ', 'ﺳ', 'ﺴ'],
            'ش' => ['ش', 'ﺶ', 'ﺷ', 'ﺸ'],
            'ص' => ['ص', 'ﺺ', 'ﺻ', 'ﺼ'],
            'ض' => ['ض', 'ﺾ', 'ﺿ', 'ﻀ'],
            'ط' => ['ط', 'ﻂ', 'ﻃ', 'ﻄ'],
            'ظ' => ['ظ', 'ﻆ', 'ﻇ', 'ﻐ'],
            'ع' => ['ع', 'ﻊ', 'ﻋ', 'ﻌ'],
            'غ' => ['غ', 'ﻎ', 'ﻏ', 'ﻐ'],
            'ف' => ['ف', 'ﻒ', 'ﻓ', 'ﻔ'],
            'ق' => ['ق', 'ﻖ', 'ﻗ', 'ﻘ'],
            'ك' => ['ك', 'ﻚ', 'ﻛ', 'ﻜ'],
            'ل' => ['ل', 'ﻞ', 'ﻟ', 'ﻠ'],
            'م' => ['م', 'ﻢ', 'ﻣ', 'ﻤ'],
            'ن' => ['ن', 'ﻦ', 'ﻧ', 'ﻨ'],
            'ه' => ['ه', 'ﻪ', 'ﻫ', 'ﻬ'],
            'ة' => ['ة', 'ﺔ', 'ﺓ', 'ﺔ'],
            'و' => ['و', 'ﻮ', 'ﻭ', 'ﻮ'],
            'ؤ' => ['ؤ', 'ﺆ', 'ﺅ', 'ﺆ'],
            'ي' => ['ي', 'ﻲ', 'ﻳ', 'ﻴ'],
            'ى' => ['ى', 'ﻰ', 'ﻯ', 'ﻰ'],
            'ئ' => ['ئ', 'ﺊ', 'ﺋ', 'ﺌ'],
            'ء' => ['ء', 'ء', 'ء', 'ء'],
            'لا' => ['ﻻ', 'ﻼ', 'ﻻ', 'ﻼ'], // Special ligature
        ];
        
        // Non-connecting characters (don't connect to next letter)
        $nonConnecting = ['ا', 'أ', 'إ', 'آ', 'د', 'ذ', 'ر', 'ز', 'و', 'ؤ', 'ء'];
        
        // Convert to array of characters
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        
        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            
            // Handle spaces and non-Arabic
            if ($char === ' ' || !isset($arabicChars[$char])) {
                $result[] = $char;
                continue;
            }
            
            // Check for لا ligature
            if ($char === 'ل' && isset($chars[$i + 1]) && $chars[$i + 1] === 'ا') {
                // Determine form for لا
                $prevConnects = false;
                if ($i > 0) {
                    $prevChar = $chars[$i - 1];
                    if (isset($arabicChars[$prevChar]) && !in_array($prevChar, $nonConnecting) && $prevChar !== ' ') {
                        $prevConnects = true;
                    }
                }
                
                $form = $prevConnects ? 1 : 0; // final or isolated
                $result[] = $arabicChars['لا'][$form];
                $i++; // Skip next character (ا)
                continue;
            }
            
            // Determine form
            $prevConnects = false;
            $nextConnects = false;
            
            // Check previous character
            if ($i > 0) {
                $prevChar = $chars[$i - 1];
                if (isset($arabicChars[$prevChar]) && !in_array($prevChar, $nonConnecting) && $prevChar !== ' ') {
                    $prevConnects = true;
                }
            }
            
            // Check next character
            if ($i < count($chars) - 1) {
                $nextChar = $chars[$i + 1];
                if (isset($arabicChars[$nextChar]) && $nextChar !== ' ') {
                    $nextConnects = true;
                }
            }
            
            // Select form: 0=isolated, 1=final, 2=initial, 3=medial
            $form = 0; // isolated
            if ($prevConnects && $nextConnects && !in_array($char, $nonConnecting)) {
                $form = 3; // medial
            } elseif ($prevConnects && !in_array($char, $nonConnecting)) {
                $form = 1; // final
            } elseif ($nextConnects && !in_array($char, $nonConnecting)) {
                $form = 2; // initial
            }
            
            $result[] = $arabicChars[$char][$form];
        }
        
        // Only reverse if requested (default: no reversal for natural flow)
        if ($reverse) {
            $result = array_reverse($result);
        }
        
        return implode('', $result);
    }
}
?>
