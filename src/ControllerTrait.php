<?php

namespace A17\TwillTransformers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Exceptions\MissingTransformer;
use App\Exceptions\MissingRepositoryClass;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Exceptions\Repository;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

trait ControllerTrait
{
    use HasConfig;

    /**
     * @return bool
     */
    public function isJsonResult()
    {
        return (config('app.debug') || app()->environment() !== 'production') &&
            Str::startsWith(request()->query('output'), 'json');
    }

    /**
     * @param null $data
     * @param null $view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|null
     */
    public function view($data = null, $view = null, $transformerClass = null)
    {
        if (!isset($this->repositoryClass) && !isset($transformerClass)) {
            Repository::missingClass(__CLASS__);
        }

        $data = $this->viewData($data, $transformerClass);

        if ($this->isJsonResult()) {
            return $this->extractJsonData($data);
        }

        return view(
            $view ?? ($data['layout_name'] ?? 'front.layout'),
            $data
        );
    }

    public function viewData($data = null, $transformerClass =  null)
    {
        $data =
            isset($this->repositoryClass) && $this->notTransformed($data)
                ? app($this->repositoryClass)->makeViewData(
                $data,
                $transformerClass
            )
                : $data;

        if (
            (blank($data) || $this->notTransformed($data)) &&
            filled($transformerClass)
        ) {
            $data = app($transformerClass)
                ->setData($data)
                ->transform();
        } elseif (blank($data)) {
            Transformer::dataNotTransformed(__CLASS__);
        }

        return $data;
    }

    protected function transform($data, $transformerClass = null)
    {
        return $this->getTransformer($data, $transformerClass)->transform();
    }

    protected function getTransformer($data, $transformerClass)
    {
        if (
            filled($class = $transformerClass) ||
            filled($class = $this->transformerClass ?? null)
        ) {
            $transformer = app($class)->setData($data);
        }

        if (filled($class = $this->repositoryClass ?? null)) {
            $transformer = app($this->repositoryClass)->makeViewDataTransformer(
                $data,
            );
        }

        if (isset($transformer)) {
            return $transformer;
        }

        TransformerException::missingOnController();
    }

    public function makeView($view = null, $data = null)
    {
        return $view ??
            ((isset($this->view) ? $this->view : null) ??
                ($this->getTemplateName($data) ??
                    $this->config('templates.default')));
    }

    public function getTemplateName($data)
    {
        return $data['template_name'] ??
            ($data['templateName'] ??
                ($this->templateName ?? ($this->template_name ?? null)));
    }

    protected function previewData($item)
    {
        return $this->repository->makeViewData($item);
    }

    public function notTransformed($data)
    {
        $transformed1 = isset($data->transformed) ? $data->transformed : false;
        $transformed2 = isset($data['transformed'])
            ? $data['transformed']
            : false;

        return !$transformed1 && !$transformed1;
    }

    public function extractJsonData($data)
    {
        if (($json = request()->query('output')) === 'json') {
            return $data;
        }

        return Arr::get($data, Str::after($json, 'json.'));
    }
}
