<?php

namespace A17\TwillTransformers\Support;

use Illuminate\Support\Str;

class BladeTransformer
{
    public function compileTransformer($code)
    {
        $code = trim($code);

        if (class_exists($code)) {
            return '
                <?php
                    foreach('.$code.'::blade("'.$code.'", get_defined_vars()) as $var => $value) {
                        $$var = $value;
                    }
                ?>
            ';
        }

        return $code;
    }

    public static function transform($transformerClass, $bladeDefinedVars): array
    {
        $transformer = new $transformerClass();

        $bladeDefinedVars['__blast'] = $transformer->setData($bladeDefinedVars['__data'])->transform();

        $bladeDefinedVars['__blast']['is_fake_data'] = false;

        if (
            blank($bladeDefinedVars['__blast']) &&
            method_exists($transformer, 'transformFakeData')
        ) {
            $bladeDefinedVars['__blast'] = $transformer->transformFakeData() ?? [];

            $bladeDefinedVars['__blast']['is_fake_data'] = true;
        }

        return $bladeDefinedVars['__blast'];
    }
}
