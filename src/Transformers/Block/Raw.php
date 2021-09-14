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
            'type' => $this->transformBlockType($this->type),

            'data' => $this->transformRawBlockData($this),
        ];
    }

    /**
     * @param \A17\TwillTransformers\Transformers\Block $block
     * @return \Illuminate\Support\Collection
     */
    protected function transformBlockContent($block): Collection
    {
        $data = collect($block->content)
            ->keys()
            ->mapWithKeys(function ($key) use ($block) {
                return [
                    $key => $this->translated($block->content[$key] ?? null),
                ];
            });

        if (filled($block->medias ?? null) && $block->medias->count() > 0) {
            $data['images'] = $block->transformImages();

            $data['image'] = $data['images'][0] ?? null;
        }

        return $data;
    }

    /**
     * @param \A17\TwillTransformers\Transformers\Block $block
     * @return \Illuminate\Support\Collection|null
     */
    protected function transformRawBlockData(Block $block)
    {
        if (!is_traversable($block->content)) {
            return null;
        }

        $data = $this->transformBlockContent($block);

        $subBlocks = $this->transformSubBlocks($block->blocks);

        return collect($data)->merge($subBlocks);
    }

    private function transformSubBlocks(Collection $blocks)
    {
        if (blank($blocks->first())) {
            return [];
        }

        $type = $this->transformBlockType(
            Str::plural($blocks->first()->type ?? 'item'),
        );

        $subBlocks = $blocks->map(function ($block) {
            $data = $this->transformBlockContent($block);

            if (blank($data) && filled($block->blocks)) {
                return $this->transformSubBlocks($block->blocks);
            }

            return $data;
        });

        if (filled($subBlocks)) {
            return [$type => $subBlocks];
        }

        return [];
    }

    public function transformBlockType($type)
    {
        return Str::kebab(Str::camel($type));
    }
}
