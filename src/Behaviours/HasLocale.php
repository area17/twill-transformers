<?php

namespace A17\TwillTransformers\Behaviours;

trait HasLocale
{
    public function locale()
    {
        if (function_exists('locale')) {
            return locale();
        }

        return app()->getLocale();
    }

    public function setLocalLocale($locale)
    {
        config([
            'app.locale' => $locale,
            'translatable.locale' => $locale,
        ]);
    }
}
