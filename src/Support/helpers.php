<?php

use A17\Twill\Models\Block as BlockModel;
use App\Transformers\Block as BlockTransformer;

if (!function_exists('swap_class')) {
    function swap_class($original, $new, $object)
    {
        $original = sprintf('O:%s:"%s"', strlen($original), $original);

        $new = sprintf('O:%s:"%s"', strlen($new), $new);

        return unserialize(str_replace($original, $new, serialize($object)));
    }
}

if (!function_exists('_transform')) {
    function _transform($model)
    {
        return $model instanceof BlockModel
            ? (new BlockTransformer($model))->transform()
            : $model->transform();
    }
}
