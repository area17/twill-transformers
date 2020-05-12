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
        if (is_string($source)) {
            return $source;
        }

        $translated = is_array($source)
            ? $this->getTranslated($source)
            : $this->translatedInput($source, $this->getActiveLocale());

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
    function getTranslated($text)
    {
        if (is_string($text)) {
            return ___($text, locale());
        }

        if (is_array($text)) {
            return $text[$this->getActiveLocale() ?? locale()] ??
                ($text[fallback_locale()] ?? '');
        }

        return null;
    }
}
