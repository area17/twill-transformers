<?php

namespace A17\TwillTransformers\Support;

class Croppings
{
    /**
     * Block editor
     */
    const BLOCK_EDITOR_ROLE_NAME = self::FREE_RATIO_ROLE_NAME;
    const BLOCK_EDITOR_CROP_NAME = self::FREE_RATIO_CROP_NAME;

    const BLOCK_EDITOR = self::FREE_RATIO;

    /**
     * Free ratio
     */
    const FREE_RATIO_ROLE_NAME = 'image';
    const FREE_RATIO_CROP_NAME = 'default';

    const FREE_RATIO = [
        self::FREE_RATIO_ROLE_NAME => [
            self::FREE_RATIO_CROP_NAME => [
                [
                    'name' => 'Default',

                    'ratio' => null,
                ] + self::EXTRA_PARAMS,
            ],
        ],
    ];

    const EXTRA_PARAMS = [
        'extra' => [
            'lqip' => ['w' => 5, 'fit' => 'max', 'auto' => 'format'],

            'srcset' => [
                [
                    '__glue' => ', ',
                    '__items' => [
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '200 200w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '400 400w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '800 800w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '1200 1200w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '1440 1440w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '1800 1800w',
                        ],
                        [
                            'auto' => 'format',
                            'q' => 85,
                            'fit' => 'max',
                            'w' => '2600 2600w',
                        ],
                    ],
                ],
            ],
        ],
    ];
}
