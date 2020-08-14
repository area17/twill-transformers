<?php

namespace A17\TwillTransformers;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->publishConfig();

        $this->configureViewMappings();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/twill-transformers.php',
            'twill-transformers',
        );
    }

    public function publishConfig()
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

    protected function configureViewMappings()
    {
        if (!config('twill-transformers.blocks.views.mappings.configure')) {
            return;
        }

        config()->set(
            'twill.block_editor.block_views_mappings',
            $this->generateBlockViewsMappings()->toArray(),
        );
    }

    public function blockMappingsTemplate()
    {
        return config('twill-transformers.blocks.views.mappings.template');
    }

    protected function getBlockList()
    {
        return collect(
            config('twill.block_editor.blocks') ?? [] +
                config('twill.block_editor.repeaters') ?? [],
        );
    }

    protected function generateBlockViewsMappings()
    {
        return $this->getBlockList()
            ->keys()
            ->mapWithKeys(
                fn($block) => [
                    $block => $this->blockMappingsTemplate(),
                ],
            );
    }
}
