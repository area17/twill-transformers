<?php

namespace A17\TwillTransformers\Transformers\Block;

use A17\TwillTransformers\Transformers\Block;

class Raw extends Block
{
    /**
     * @return array|\Illuminate\Support\Collection|null
     */
    public function transform()
    {
        return [
            'data' => $this->transformRawBlockData($this),
        ];
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
            $data['image'] = [
                'type' => 'image',

                'data' => $block->transformMedia($block),
            ];
        }

        return $data;
    }
}
