<?php

namespace A17\TwillTransformers\Exceptions;

class Template extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function notFound()
    {
        throw new self(
            'Template (view) name is missing, please define the property ' .
                "'\$templateName' on your Transformer, or on the config, or implement " .
                "the method 'getTemplate()'.",
        );
    }
}
