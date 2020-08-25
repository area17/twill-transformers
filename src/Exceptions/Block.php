<?php

namespace A17\TwillTransformers\Exceptions;

class Block extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function classNotFound($type)
    {
        throw new self("Block class not found for block type '{$type}'.");
    }

    /**
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function appRootBlockTransformerNotFound()
    {
        throw new self(
            'App root Block transformer not found. You must define App\Transformers\Block::class or App\Transformers\Block\Block::class.',
        );
    }
}
