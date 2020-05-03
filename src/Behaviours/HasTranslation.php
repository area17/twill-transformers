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
        $translated = is_array($source)
            ? $this->getTranslated($source)
            : $this->translatedInput($source);

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
        if (!is_array($text)) {
            return ___($text, locale());
        }

        return $text[locale()] ?? ($text[fallback_locale()] ?? '');
    }
}
