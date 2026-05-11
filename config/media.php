<?php

return [
    'preview_variant' => 'thumbnail',

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