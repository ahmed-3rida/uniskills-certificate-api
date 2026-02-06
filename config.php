<?php
/**
 * Certificate Generator Configuration
 * عدل هذه الإعدادات حسب احتياجك
 */

return [
    // Default positions (used as fallback)
    'positions' => [
        'student_name' => [
            'y' => 132,
            'font_size' => 11,
            'centered' => true,
        ],
        'course_name' => [
            'y' => 173,
            'font_size' => 10,
            'centered' => true,
        ],
        'date' => [
            'x' => 83,
            'y_from_bottom' => 40,
            'font_size' => 5,
            'centered' => false,
        ],
        'instructor' => [
            'x_from_right' => 83,
            'y_from_bottom' => 40,
            'font_size' => 5,
            'centered' => false,
        ],
    ],
    
    // Arabic-specific positions (مواضع خاصة بالعربي)
    // عدّل هذه المواضع للشهادة العربية
    'positions_ar' => [
        'student_name' => [
            'y' => 130,           // موضع اسم الطالب من الأعلى
            'font_size' => 9,    // حجم الخط
            'centered' => true,
        ],
        'course_name' => [
            'y' => 170,           // موضع اسم الدورة
            'font_size' => 7,
            'centered' => true,
        ],
        'date' => [
            'x' => 86,            // موضع التاريخ من اليسار
            'y_from_bottom' => 42, // من الأسفل
            'font_size' => 4,
            'centered' => false,
        ],
        'instructor' => [
            'x_from_right' => 81, // موضع المدرب من اليمين
            'y_from_bottom' => 43,
            'font_size' => 4,
            'centered' => false,
        ],
    ],
    
    // English-specific positions (مواضع خاصة بالإنجليزي)
    // عدّل هذه المواضع للشهادة الإنجليزية
    'positions_en' => [
        'student_name' => [
            'y' => 132,           // موضع اسم الطالب من الأعلى
            'font_size' => 10,    // حجم الخط
            'centered' => true,
        ],
        'course_name' => [
            'y' => 168,           // موضع اسم الدورة
            'font_size' => 7,
            'centered' => true,
        ],
        'date' => [
            'x' => 81,            // موضع التاريخ من اليسار
            'y_from_bottom' => 43, // من الأسفل
            'font_size' => 4,
            'centered' => true,
        ],
        'instructor' => [
            'x_from_right' => 80, // موضع المدرب من اليمين
            'y_from_bottom' => 43,
            'font_size' => 4,
            'centered' => true,
        ],
    ],
    
    // Image settings
    'image' => [
        'quality' => 95,          // JPEG quality (1-100)
        'base_width' => 400,      // Base width for scaling calculations
    ],
    
    // Font settings
    'font' => [
        'path' => __DIR__ . '/fonts/Cairo-Bold.ttf',
        'fallback' => true,       // Use built-in font if custom font not found
    ],
    
    // Security
    'allowed_origins' => ['*'],   // CORS allowed origins (* = all)
    
    // Paths
    'templates_dir' => __DIR__ . '/templates',
];
