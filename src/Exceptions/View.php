<?php

namespace A17\TwillTransformers\Exceptions;

class View extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function missing()
    {
        throw new self(
            'View name is missing. ' .
                'You can either define it as layout_name or template_name, ' .
                'or even on your Controller as layoutName or templateName',
        );
    }
}
