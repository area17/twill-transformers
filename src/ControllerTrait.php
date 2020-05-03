<?php

namespace A17\TwillTransformers\Controllers;

use App\Exceptions\MissingRepositoryClass;

trait ControllerTrait
{
    /**
     * @return bool
     */
    public function isJsonResult()
    {
        return (config('app.debug') || app()->environment() !== 'production') &&
            request()->query('output') === 'json';
    }

    /**
     * @param null $data
     * @param null $view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|null
     */
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
