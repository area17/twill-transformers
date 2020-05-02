<?php

namespace A17\TwillTransformers\Controllers;

use App\Exceptions\MissingRepositoryClass;

trait ControllerTrait
{
    private function isJsonResult()
    {
        return (config('app.debug') || app()->environment() !== 'production') &&
            request()->query('output') === 'json';
    }

    public function view($data = null, $view = null)
    {
        $data = $this->repository->makeViewData($data);

        if ($this->isJsonResult()) {
            return $data;
        }

        return view(
            $view ?? config('twill-transformers.controllers.templates.default'),
            $data,
        );
    }
}
