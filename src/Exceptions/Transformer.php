<?php

namespace A17\TwillTransformers\Exceptions;

class Transformer extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function missingOnController()
    {
        throw new self(
            'Transformer class not available. ' .
                "You can either create a '\$repositoryClass' property " .
                "or a '\$transformerClass' on your Controller, or you " .
                'can also pass a Transformer to be used directly.',
        );
    }

    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function missingOnRepository()
    {
        throw new self(
            "Transformer class not available. You probably need to create a '\$transformerClass' on your Repository.",
        );
    }

    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function methodNotFound($method)
    {
        throw new self(
            "Transformer or transformer method not found: '$method()'",
        );
    }

    public static function dataNotTransformed($class)
    {
        throw new self(
            "Data was not transformed on class '$class', maybe a transformer was not available on the repository or not passed as argument.",
        );
    }

    public static function dataAlreadySet($class)
    {
        throw new self("Data for '$class', was already set.");
    }
}
