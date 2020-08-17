<?php

namespace A17\TwillTransformers\Behaviours;

use Illuminate\Support\Str;
use A17\TwillTransformers\Transformers\Block;
use A17\TwillTransformers\Transformers\Block as BlockTransformer;

trait HasBlocks
{
    /**
     * @param $rootBlockId
     * @param $allBlocks
     * @return mixed
     */
    protected function organizeBlocks($rootBlockId, $allBlocks)
    {
        if (blank($allBlocks)) {
            return collect();
        }

        return $allBlocks
            ->where('parent_id', $rootBlockId)
            ->map(function ($blockModel) use ($allBlocks) {
                $block = new BlockTransformer();

                $block->setActiveLocale($this);

                $block->content = $blockModel->content;

                $block->block = $blockModel;

                $block->type = $blockModel->type;

                $block->setBrowsers($this->renderBrowsers($blockModel));

                $block->pushBlocks(
                    $this->organizeBlocks($block->block->id, $allBlocks),
                );
                return $block;
            });
    }

    /**
     * @param $blockModel
     * @return \Illuminate\Support\Collection
     */
    protected function renderBrowsers($blockModel)
    {
        $models = [];

        foreach (
            $blockModel->content['browsers'] ?? []
            as $browserRelation => $ids
        ) {
            $models[$browserRelation] = $models[$browserRelation] ?? collect();

            foreach ($ids as $id) {
                $models[$browserRelation]->push(
                    $this->getModelFromBrowserName($browserRelation, $id),
                );
            }
        }

        return collect($models);
    }

    /**
     * @return array|\Illuminate\Support\Collection|mixed|string|void|null
     */
    public function transformBlocks($model = null)
    {
        $model ??= $this;

        $blocks = $this->organizeBlocks(null, $model->blocks); // organize root blocks

        if (blank($blocks)) {
            return null;
        }

        return (new BlockTransformer($blocks->values()))
            ->setActiveLocale($model)
            ->transform();
    }

    /**
     * @param $browserName
     * @param $id
     * @return mixed
     */
    protected function getModelFromBrowserName($browserName, $id)
    {
        $browserName = Str::beforeLast($browserName, ':');

        $class =
            $this->config('namespaces.app.models') .
            '\\' .
            Str::singular(Str::studly($browserName));

        return $class::find($id);
    }

    protected function isBlock($element)
    {
        return $element instanceof Block || isset($element['block']);
    }

    protected function isBlockCollection($element)
    {
        return is_traversable($element) &&
            isset($element[0]) &&
            $element[0] instanceof BlockTransformer;
    }
}
