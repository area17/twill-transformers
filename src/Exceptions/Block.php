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
}
