<?php

return [
    'preview_variant' => 'thumbnail',

    'uploads' => [
        'accepted_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/bmp',
            'image/svg+xml',
            'application/pdf',
            'text/plain',
            'text/markdown',
            'text/csv',
            'application/json',
            'application/xml',
            'text/xml',
            'application/zip',
            'application/x-zip-compressed',
            'application/vnd.rar',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ],

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