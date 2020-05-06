<?php

namespace A17\TwillTransformers\Transformers;

use Illuminate\Support\Str;
use A17\TwillTransformers\Transformer;
use A17\TwillTransformers\Exceptions\Block as BlockException;

class Block extends Transformer
{
    public $__browsers = [];

    protected $__blocks = [];

    /**
     * Block constructor.
     *
     * @param null $data
     */
    public function __construct($data = null)
    {
        $this->__browsers = collect();

        $this->__blocks = collect();

        parent::__construct($data);
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBlocks()
    {
        if ($this->__blocks->count() > 0) {
            return $this->__blocks;
        }

        if (isset($this->data->__blocks)) {
            return $this->data->__blocks;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $blocks
     */
    public function setBlocks($blocks): void
    {
        $this->__blocks = $blocks;
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function getBrowsers()
    {
        if ($this->__browsers->count() > 0) {
            return $this->__browsers;
        }

        if (isset($this->data->__browsers)) {
            return $this->data->__browsers;
        }

        return collect();
    }

    /**
     * @param array|\Illuminate\Support\Collection $browsers
     */
    public function setBrowsers($browsers): void
    {
        $this->__browsers = $browsers;
    }

    /**
     * @return array|null
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    protected function transformBlock()
    {
        if (filled($transformer = $this->findBlockTransformer($this))) {
            return $this->transformAndAddType($transformer);
        }

        if (filled($raw = $this->transformBlockRaw())) {
            return $raw;
        }

        BlockException::classNotFound($this->block->type);
    }

    /**
     * @param null $block
     * @return \A17\TwillTransformers\Transformer|null
     */
    public function findBlockTransformer($block = null)
    {
        $block ??= $this;

        $transformer = $this->findTransformerByMethodName(
            'transformBlock' . Str::studly($block->type),
        );

        if (blank($transformer)) {
            return null;
        }

        return $this->transformerSetDataOrTransmorph($transformer, $block);
    }

    /**
     * @return array|\Illuminate\Support\Collection|null
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public function transform()
    {
        if ($result = $this->transformBlockCollection()) {
            return $result;
        }

        return $this->transformGenericBlock();
    }

    /**
     * @param $blocks
     */
    public function pushBlocks($blocks)
    {
        collect($blocks)->each(function ($block) {
            $this->__blocks->push($block);
        });
    }

    protected function transformBlockCollection()
    {
        if (!$this->isBlockCollection($collection = $this->data)) {
            return null;
        }

        return collect($collection)->map(function ($item) {
            return $item instanceof Block
                ? $item->transform()
                : (new self($item))->setActiveLocale($this)->transform();
        });
    }

    protected function transformGenericBlock()
    {
        return $this->transformBlock() ?? null;
    }

    protected function transformAndAddType($transformer)
    {
        $result = $transformer->transform();

        // The type of the block may change during transform()
        return ['type' => $transformer->type ?? null] + $transformer->transform();
    }
}
