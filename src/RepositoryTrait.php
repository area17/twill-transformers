<?php

namespace A17\TwillTransformers;

trait RepositoryTrait
{
    public function makeViewData($subject = null)
    {
        $subject = $subject ?? [];

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
                'frontendTemplate' =>
                    $this->getFrontendTemplate($subject) ?? null,
                'type' => $this->repositoryType,
                'data' => $subject,
            ])
            ->transform();
    }
}
