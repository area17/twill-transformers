<?php

namespace A17\TwillTransformers\Transformers\Block;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use A17\TwillTransformers\Transformers\Block;

class Raw extends Block
{
    /**
     * @return array|\Illuminate\Support\Collection|null
     */
    public function transform()
    {
        return [
            'type' => Str::kebab(Str::camel($this->type)),

            'data' => $this->transformRawBlockData($this),
        ];
    }

    /**
     * @param \A17\TwillTransformers\Transformers\Block $block
     * @return \Illuminate\Support\Collection
     */
    protected function transformBlockContent(Block $block): Collection
    {
        $data = collect($block->content)
            ->keys()
            ->mapWithKeys(function ($key) use ($block) {
                $content = array_key_exists(
                    app()->getLocale(),
                    collect($block->content[$key] ?? [])->toArray(),
                )
                    ? $block->content[$key][app()->getLocale()]
                    : $block->content[$key] ?? null;

                return [$key => $content];
            });

        if (filled($block->medias ?? null) && $block->medias->count() > 0) {
            $data['image'] = $block->transformMedia($block);
        }

        return $data;
    }

    /**
     * @param \A17\TwillTransformers\Transformers\Block $block
     * @return \Illuminate\Support\Collection|null
     */
    protected function transformRawBlockData(Block $block)
    {
        if (!is_array($block->content)) {
            return null;
        }

        $data = $this->transformBlockContent($block);

        $subBlocks = $this->transformSubBlocks($block->blocks);

        return collect($data)->merge($subBlocks);
    }

    private function transformSubBlocks(Collection $blocks)
    {
        $subBlocks = $blocks->map(function ($block) {
            $data = $this->transformBlockContent($block);

            if (blank($data) && filled($block->blocks)) {
                return $this->transformSubBlocks($block->blocks);
            }

            return $data;
        });

        if (filled($subBlocks)) {
            return ['items' => $subBlocks];
        }

        return [];
    }
}
