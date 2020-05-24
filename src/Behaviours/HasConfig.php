<?php

namespace A17\TwillTransformers\Behaviours;

trait HasConfig
{
    public function config($path)
    {
        return config("twill-transformers.{$path}");
    }
}
