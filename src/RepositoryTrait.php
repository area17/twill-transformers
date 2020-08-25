<?php

namespace A17\TwillTransformers;

use App\Support\Templates;
use A17\Twill\Models\Model;
use Illuminate\Support\Str;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Exceptions\Repository;
use A17\TwillTransformers\Behaviours\ClassFinder;
use Astrotomic\Translatable\Contracts\Translatable;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

trait RepositoryTrait
{
    use ClassFinder, HasConfig;

    public function makeViewData($data = [], $transformerClass = null)
    {
        return $this->makeViewDataTransformer(
            $data,
            $transformerClass,
        )->transform();
    }

    public function transform($data)
    {
        return $this->makeViewData($data);
    }

    public function makeViewDataTransformer(
        $subject = [],
        $transformerClass = null
    ) {
        if (is_numeric($subject)) {
            $subject = $this->getById($subject);
        }

        $transformer = app($transformerClass ?? $this->getTransformerClass());

        return $transformer->setData([
            'template_name' =>
                $this->getTemplateName($subject, $transformer) ?? null,
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

        return collect($objects)->reduce(
            fn($name, $object) => $name ??
                $this->getTemplateNameFromObject($object),
        );
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
                ->contains($locale = locale())
                ? $locale
                : fallback_locale();
        }

        return locale();
    }

    protected function getTemplateNameFromObject($object)
    {
        $templateName = isset($object->templateName)
            ? $object->templateName
            : (isset($object->template_name)
                ? $object->template_name
                : null);

        if (blank($templateName)) {
            try {
                $templateName = $object['templateName'];
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object['template_name'];
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object->getTemplateName();
            } catch (\Throwable $exception) {
            }
        }

        if (blank($templateName)) {
            try {
                $templateName = $object->getTemplate();
            } catch (\Throwable $exception) {
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
