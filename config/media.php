<?php

return [
    'preview_variant' => 'thumbnail',

    'favicons' => [
        'variants' => [
            'favicon-16' => ['width' => 16, 'height' => 16, 'mode' => 'crop', 'format' => 'png'],
            'favicon-32' => ['width' => 32, 'height' => 32, 'mode' => 'crop', 'format' => 'png'],
            'apple-touch-icon' => ['width' => 180, 'height' => 180, 'mode' => 'crop', 'format' => 'png'],
            'android-chrome-192' => ['width' => 192, 'height' => 192, 'mode' => 'crop', 'format' => 'png'],
            'android-chrome-512' => ['width' => 512, 'height' => 512, 'mode' => 'crop', 'format' => 'png'],
        ],
    ],

    'images' => [
        'optimize' => true,
        'max_width' => 1920,
        'max_height' => 1920,
        'jpg_quality' => 82,
        'webp_quality' => 80,
        'convert_to_webp' => true,
        'keep_original' => true,
        'create_thumbnails' => true,

        'sizes' => [
            'thumbnail' => [
                'width' => 300,
                'height' => 300,
                'mode' => 'crop',
            ],
            'medium' => [
                'width' => 768,
                'height' => null,
                'mode' => 'resize',
            ],
            'large' => [
                'width' => 1280,
                'height' => null,
                'mode' => 'resize',
            ],
        ],
    ],
];