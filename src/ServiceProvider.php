<?php

namespace A17\TwillTransformers;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/twill-transformers.php' => config_path(
                    'twill-transformers.php',
                ),
            ],
            'config',
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/twill-transformers.php',
            'twill-transformers',
        );
    }
}
