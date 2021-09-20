<?php

namespace A17\TwillTransformers\Transformers\Block;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use A17\Twill\Models\Block as BlockModel;
use A17\TwillTransformers\Transformers\Block as BlockTransformer;

class Raw extends BlockTransformer
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
     * @param \A17\TwillTransformers\Transformers\Block|A17\Twill\Models\Block $block
     * @return \Illuminate\Support\Collection
     */
    protected function transformBlockContent($block): Collection
    {
        $block = $this->encapsulateBlock($block);

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
    protected function transformRawBlockData($block)
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

    public function encapsulateBlock($block)
    {
        if ($block instanceof Block) {
            return $block;
        }

        return new BlockTransformer($block);
    }
}
