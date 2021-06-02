<?php

namespace A17\TwillTransformers\Behaviours;

trait HasTranslation
{
    /**
     * @param $source
     * @param null $property
     * @return array|mixed|string|null
     */
    protected function translated($source, $property = null)
    {
        if (is_json($source)) {
            $source = json_decode($source, true);
        }

        if (!is_traversable($source)) {
            return $source;
        }

        $translated = $this->getTranslated($source);

        if (blank($property)) {
            return $translated;
        }

        return [
            'data' => [
                $property => $translated,
            ],
        ];
    }

    /**
     * @param $text
     * @return array|mixed|string|null
     */
    function getTranslated($input)
    {
        if (is_string($input)) {
            return ___($input, locale());
        }

        if (is_traversable($input)) {
            return $input[$this->getActiveLocale() ?? locale()] ??
                ($input[fallback_locale()] ?? '');
        }

        return null;
    }
}
