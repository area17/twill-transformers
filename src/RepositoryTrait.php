<?php

namespace A17\TwillTransformers;

use App\Support\Templates;
use A17\Twill\Models\Model;
use Illuminate\Support\Str;
use A17\TwillTransformers\Transformer;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Behaviours\HasLocale;
use A17\TwillTransformers\Exceptions\Repository;
use A17\TwillTransformers\Behaviours\ClassFinder;
use Astrotomic\Translatable\Contracts\Translatable;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

trait RepositoryTrait
{
    use ClassFinder, HasConfig, HasLocale;

    public function makeViewData(
        $data = [],
        $transformerClass = null,
        $controller = null
    ) {
        return $this->makeViewDataTransformer(
            $data,
            $transformerClass,
            $controller,
        )->transform();
    }

    public function transform($data)
    {
        return $this->makeViewData($data);
    }

    public function makeViewDataTransformer(
        $subject = [],
        $transformerClass = null,
        $controller = null
    ) {
        if (is_numeric($subject)) {
            $subject = $this->getById($subject);
        }

        $transformer = app($transformerClass ?? $this->getTransformerClass());

        return $transformer->setData([
            'template_name' =>
                $this->getTemplateName($transformer, $subject, $controller) ??
                null,
            'type' => $this->getRepositoryType(),
            'data' => $subject,
            'global' => $this->generateGlobalData(),
            'active_locale' => $this->getActiveLocale($subject),
        ]);
    }

    /**
     * @return string
     */
    public function getTemplateName(...$objects)
    {
        $objects[] = $this;

        $templateName = collect($objects)->reduce(
            fn($name, $object) => $name ??
                $this->getTemplateNameFromObject($object),
        );

        if (blank($templateName)) {
            throw new \Exception(
                'The templateName property could not be found on repository, controller and transformer.',
            );
        }

        return $templateName;
    }

    /**
     * @return string
     */
    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;
    }

    protected function getActiveLocale($model)
    {
        if (
            $model instanceof Model &&
            isset($model->translations) &&
            filled($model->translations ?? null)
        ) {
            return $model->translations
                ->pluck('locale')
                ->contains($locale = $this->locale())
                ? $locale
                : fallback_locale();
        }

        return $this->locale();
    }

    protected function getTemplateNameFromObject($object)
    {
        $templateName = null;

        $items = ['template_name', 'layout_name', 'template'];

        $names = [];

        foreach ($items as $item) {
            $names[] = $item;
            $names[] = Str::camel($item);
            $names[] = Str::studly($item);
            $names[] = 'get' . Str::studly($item);
        }

        if (is_string($object) && class_exists($object)) {
            $object = app($object);
        }

        $isTraversable = is_traversable($object);

        foreach ($names as $name) {
            if (
                blank($templateName) &&
                $isTraversable &&
                isset($object[$name])
            ) {
                $templateName = $object[$name];
            }

            if (
                blank($templateName) &&
                is_object($object) &&
                property_exists($object, $name)
            ) {
                $templateName = $object->$name;
            }

            if (
                blank($templateName) &&
                is_object($object) &&
                !$object instanceof Transformer &&
                method_exists($object, $name)
            ) {
                $templateName = $object->$name();
            }

            if (filled($templateName)) {
                break;
            }
        }

        return $templateName;
    }

    public function getTransformerClass()
    {
        if (filled($this->transformerClass ?? null)) {
            return $this->transformerClass;
        }

        if ($class = $this->inferTransformerClassFromRepositoryName()) {
            return $class;
        }

        TransformerException::missingOnRepository();
    }

    public function getRepositoryType()
    {
        return $this->repositoryType ?? null;
    }

    public function inferTransformerClassFromRepositoryName()
    {
        $class = (string) Str::of(__CLASS__)
            ->afterLast('\\')
            ->beforeLast('Repository');

        return $this->findTransformerClass($class);
    }

    public function generateGlobalData()
    {
        if (method_exists($this, 'makeGlobalData')) {
            return $this->makeGlobalData();
        }

        return [];
    }
}
