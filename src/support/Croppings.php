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
                ],
            ],
        ],
    ];
}
