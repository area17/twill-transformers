<?php

namespace A17\TwillTransformers;

use App\Support\Templates;
use A17\Twill\Models\Model;
use A17\TwillTransformers\Exceptions\Repository;
use Astrotomic\Translatable\Contracts\Translatable;

trait RepositoryTrait
{
    public function makeViewData($subject = [])
    {
        if (is_numeric($subject)) {
            $subject = $this->getById($subject);
        }

        if (blank($this->transformerClass ?? null)) {
            throw new \Exception(
                'Class ' .
                    __CLASS__ .
                    ' misses the transformer class definition.',
            );
        }

        return app($this->transformerClass)
            ->setData([
                'template_name' => $this->getTemplateName($subject) ?? null,
                'type' => $this->repositoryType,
                'data' => $subject,
                'active_locale' => $this->getActiveLocale($subject),
            ])
            ->transform();
    }

    /**
     * @return string
     */
    public function getTemplateName($object = null)
    {
        return $this->templateName ?? Repository::templateNameNotDefined();
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
        if (filled($model->translations ?? null)) {
            return $model->translations->pluck('locale')->contains(locale())
                ? locale()
                : fallback_locale();
        }

        return locale();
    }
}
