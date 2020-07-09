<?php

use A17\Twill\Models\Block;
use Illuminate\Database\Eloquent\Model;
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
    function _transform(...$data)
    {
        $transformer = $data[0] ?? null;

        if (blank($transformer)) {
            return null;
        }

        foreach ($data as $datum) {
            if (isset($datum['transformed']) && $datum['transformed']) {
                return $datum;
            }
        }

        if ($transformer instanceof Block) {
            $transformer = new BlockTransformer($transformer);
        }

        return $transformer->transform();
    }
}

if (!function_exists('locale')) {
    function locale()
    {
        return app()->getLocale();
    }
}

if (!function_exists('set_local_locale')) {
    function set_local_locale($locale)
    {
        config([
            'app.locale' => $locale,
            'translatable.locale' => $locale,
        ]);
    }
}

if (!function_exists('convert_blanks_to_nulls')) {
    function convert_blanks_to_nulls($subject)
    {
        if (is_array($subject)) {
            $subject = collect($subject);
        }

        $subject = $subject->map(function ($item, $key) {
            if (blank($item)) {
                return null;
            }

            if (is_traversable($item)) {
                return convert_blanks_to_nulls($item);
            }

            return $item;
        });

        return $subject->toArray();
    }
}

if (!function_exists('to_array')) {
    function to_array($collection)
    {
        if (blank($collection)) {
            return $collection;
        }

        if ($collection instanceof \stdClass) {
            $collection = collect(json_decode(json_encode($collection), true));
        }

        if ($collection instanceof Model) {
            $collection = $collection->toArray();
        }

        if (!is_traversable($collection)) {
            return $collection;
        }

        if (is_array($collection)) {
            $collection = collect($collection);
        }

        $collection = $collection->map(function ($item) {
            if (is_traversable($item)) {
                return to_array($item);
            }

            return $item;
        });

        if (keys_are_all_numeric($collection)) {
            $collection = $collection->values();
        }

        return $collection->toArray();
    }
}
