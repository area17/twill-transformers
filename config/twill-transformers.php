<?php

use A17\Transformers\Transformers\Block;

return [
    'templates' => ['default' => 'front.template'],

    'transformers' => ['block' => Block::class],

    'namespaces' => [
        'package' => ($package = 'A17\TwillTransformers'),

        'package_transformers' => ($packageTransformers =
            $package . '\Transformers'),

        'block_transformers' => $packageTransformers . '\Block',

        'app_transformers' => 'App\Transformers',
    ],
];
