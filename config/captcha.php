<?php

return [
    'disable' => env('CAPTCHA_DISABLE', false),
    'characters' => ['2', '3', '4', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'm', 'n', 'p', 'q', 'r', 't', 'u', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'M', 'N', 'P', 'Q', 'R', 'T', 'U', 'X', 'Y', 'Z'],
    'default' => [
        'length' => env('CAPTCHA_DEFAULT_LENGTH', 9),
        'width' => env('CAPTCHA_DEFAULT_WIDTH', 120),
        'height' => env('CAPTCHA_DEFAULT_HEIGHT', 36),
        'quality' => env('CAPTCHA_DEFAULT_QUALITY', 90),
        'math' => env('CAPTCHA_DEFAULT_MATH', false),
        'expire' => env('CAPTCHA_DEFAULT_EXPIRE', 60),
        'encrypt' => env('CAPTCHA_DEFAULT_ENCRYPT', false),
    ],
    'math' => [
        'length' => env('CAPTCHA_MATH_LENGTH', 9),
        'width' => env('CAPTCHA_MATH_WIDTH', 120),
        'height' => env('CAPTCHA_MATH_HEIGHT', 36),
        'quality' => env('CAPTCHA_MATH_QUALITY', 90),
        'math' => env('CAPTCHA_MATH_MATH', true),
    ],

    'flat' => [
        'length' => env('CAPTCHA_FLAT_LENGTH', 6),
        'width' => env('CAPTCHA_FLAT_WIDTH', 160),
        'height' => env('CAPTCHA_FLAT_HEIGHT', 46),
        'quality' => env('CAPTCHA_FLAT_QUALITY', 90),
        'lines' => env('CAPTCHA_FLAT_LINES', 6),
        'bgImage' => env('CAPTCHA_FLAT_BGIMAGE', false),
        'bgColor' => env('CAPTCHA_FLAT_BGCOLOR', '#ecf2f4'),
        'fontColors' => ['#2c3e50', '#c0392b', '#16a085', '#c0392b', '#8e44ad', '#303f9f', '#f57c00', '#795548'],
        'contrast' => env('CAPTCHA_FLAT_CONTRAST', -5),
    ],
    'mini' => [
        'length' => env('CAPTCHA_MINI_LENGTH', 3),
        'width' => env('CAPTCHA_MINI_WIDTH', 60),
        'height' => env('CAPTCHA_MINI_HEIGHT', 32),
    ],
    'inverse' => [
        'length' => env('CAPTCHA_INVERSE_LENGTH', 5),
        'width' => env('CAPTCHA_INVERSE_WIDTH', 120),
        'height' => env('CAPTCHA_INVERSE_HEIGHT', 36),
        'quality' => env('CAPTCHA_INVERSE_QUALITY', 90),
        'sensitive' => env('CAPTCHA_INVERSE_SENSITIVE', true),
        'angle' => env('CAPTCHA_INVERSE_ANGLE', 12),
        'sharpen' => env('CAPTCHA_INVERSE_SHARPEN', 10),
        'blur' => env('CAPTCHA_INVERSE_BLUR', 2),
        'invert' => env('CAPTCHA_INVERSE_INVERT', true),
        'contrast' => env('CAPTCHA_INVERSE_CONTRAST', -5),
    ],
];
