<?php

namespace A17\TwillTransformers\Exceptions;

class Block extends \Exception
{
    public static function classNotFound($type)
    {
        throw new self("Block class not found for block type '{$type}'.");
    }
}
