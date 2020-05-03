<?php

if (function_exists('swap_class')) {
    function swap_class($original, $new, $object)
    {
        $original = sprintf('O:%s:"%s"', strlen($original), $original);

        $new = sprintf('O:%s:"%s"', strlen($new), $new);

        return unserialize(str_replace($original, $new, serialize($object)));
    }
}
