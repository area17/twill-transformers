<?php

namespace A17\TwillTransformers\Exceptions;

class Transformer extends \Exception
{
    /**
     * @param $method
     * @throws \A17\TwillTransformers\Exceptions\Transformer
     */
    public static function methodNotFound($method)
    {
        throw new self("Transform method not found '{$method}'.");
    }

    /**
     * @throws \A17\TwillTransformers\Exceptions\Transformer
     */
    public static function dataAlreadySet()
    {
        throw new self('Data for the transformer has already been set.');
    }
}
