<?php

namespace A17\TwillTransformers\Exceptions;

class Repository extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function missingClass($class)
    {
        throw new self(
            "The property \$repository_class is missing from class '$class', and a transformer was also not passed as parameter.",
        );
    }
}
