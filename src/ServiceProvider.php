<?php

namespace A17\TwillTransformers;

use Illuminate\Support\Facades\Blade;
use A17\TwillTransformers\Support\BladeTransformer;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->publishConfig();

        $this->configureViewMappings();

        $this->bootBladeDirectives();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/twill-transformers.php',
            'twill-transformers',
        );

        $this->registerTransformer();
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
            config('twill.block_editor.blocks') ??
                (config('twill.block_editor.repeaters') ?? []),
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

    private function bootBladeDirectives(): void
    {
        Blade::directive('transformer', function ($expression) {
            return app(BladeTransformer::class)->compileTransformer(
                $expression,
            );
        });
    }

    public function registerTransformer()
    {
        $this->app->singleton(BladeTransformer::class, function ($app) {
            return new BladeTransformer();
        });
    }
}
