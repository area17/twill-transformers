<?php

namespace A17\TwillTransformers\Transformers;

use Illuminate\Support\Str;
use A17\TwillTransformers\Transformer;
use A17\TwillTransformers\Behaviours\HasMedia;
use A17\TwillTransformers\Services\Image\Croppings;
use A17\TwillTransformers\Media as MediaTransformer;
use A17\TwillTransformers\Exceptions\Block as BlockException;

class Block extends Transformer
{
    use HasMedia;

    public $__browsers = [];

    private $__blocks = [];

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

    protected function transformBlock()
    {
        $transformer = $this->findBlockTransformer($this);

        if (filled($raw = $this->transformRawBlock())) {
            return $raw;
        }

        return [];
    }

    public function findBlockTransfomer($block = null)
    {
        $block ??= $this;

        $blockName = Str::studly($block->type);

        $appBlock =
            config('twill-transformers.app.namespaces.blocks') .
            "\{$blockName}";

        $packageBlock =
            config('twill-transformers.app.namespaces.package') .
            "\Blocks\{$blockName}";

        if (class_exists($appBlock)) {
            return new $appBlock($block);
        }

        if (class_exists($packageBlock)) {
            new $packageBlock($block);
        }

        throw BlockException::classNotFound($block->type);
    }

    protected function getFrontEndDataType($type)
    {
        switch ($type) {
            case 'visit_form_link':
            case 'visit_page_link':
                return 'activity';
        }
        return $type;
    }

    private function isBlock($element)
    {
        return isset($element['block']);
    }

    private function isBlockCollection($element)
    {
        return is_traversable($element) &&
            isset($element[0]) &&
            $element[0] instanceof Block;
    }

    protected function translated($source, $property = null)
    {
        $translated = is_array($source)
            ? get_translated($source)
            : $this->translatedInput($source);

        if (blank($property)) {
            return $translated;
        }

        return [
            'data' => [
                $property => $translated,
            ],
        ];
    }

    public function transform()
    {
        if ($this->isBlockCollection($this->data)) {
            return collect($this->data)->map(function ($item) {
                return (new self($item))->transform();
            });
        }

        $block = (new self($this->data))->transformBlock() ?? [];

        if (
            blank($block) ||
            blank($block->type ?? $this->getFrontEndDataType($this->type))
        ) {
            return [];
        }

        return [
            'type' => $block->type ?? $this->getFrontEndDataType($this->type),
        ] + $block;
    }

    public function pushBlocks($blocks)
    {
        collect($blocks)->each(function ($block) {
            $this->__blocks->push($block);
        });
    }
}
