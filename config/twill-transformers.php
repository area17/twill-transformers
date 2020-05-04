<?php

use A17\Transformers\Transformers\Block;

return [
    'templates' => ['default' => 'front.template'],

    'transformers' => ['block' => Block::class],

    'namespaces' => [
        'package' => [
            'root' => ($package = 'A17\TwillTransformers'),

            'transformers' => ($packageTransformers =
                $package . '\Transformers'),
        ],

        'block' => ['transformers' => $packageTransformers . '\Block'],

        'app' => [
            'models' => 'App\Models',
            'transformers' => 'App\Transformers',
        ],
    ],

    // Configure block_views_mappings to point all to a single template
    'blocks' => [
        'views' => [
            'mappings' => [
                'configure' => true,

                'template' => 'site/previews/blocks/block',
            ],
        ],
    ],
];
