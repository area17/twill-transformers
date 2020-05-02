<?php

namespace A17\Transformers\Transformers;

use ArrayAccess;
use ImageService;
use A17\Twill\Models\Media;
use Illuminate\Support\Str;
use A17\Transformers\Services\Image\Croppings;
use A17\Transformers\Repositories\EventRepository;
use A17\Transformers\Transformers\Media as MediaTransformer;
use A17\Transformers\Transformers\Contract as TransformerContract;

abstract class Transformer implements TransformerContract, ArrayAccess
{
    /**
     * @var array
     */
    public $data;

    public function __construct($data = null)
    {
        $this->setData($data);
    }

    /**
     * @param $browserName
     * @param $id
     * @return mixed
     */
    protected function getModelFromBrowserName($browserName, $id)
    {
        $relation = Str::singular(Str::studly($browserName));

        $class = "A17\Transformers\Models\\{$relation}";

        return $class::find($id);
    }

    private function mediaParamsForBlocks()
    {
        return Croppings::BLOCK_EDITOR_CROPS;
    }

    /**
     * @param $rootBlockId
     * @param $allBlocks
     * @return mixed
     */
    protected function organizeBlocks($rootBlockId, $allBlocks)
    {
        return $allBlocks
            ->where('parent_id', $rootBlockId)
            ->map(function ($blockModel) use ($allBlocks) {
                $block = new Block();

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
     * Called after setting data to process data before giving it the the actual transformer.
     */
    protected function preProcessData()
    {
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
     * @param $array
     * @return array
     */
    protected function sanitize($array)
    {
        return to_array($array);
    }

    /**
     * @param mixed $data
     *
     * @return \A17\Transformers\Transformers\Transformer
     */
    public function setData($data)
    {
        if (filled($this->data = $data)) {
            $this->preProcessData();
        }

        return $this;
    }

    /**
     * @return array
     */
    public function transform()
    {
        return $this->sanitize([
            'template_name' => $this->makeTemplateName(),

            'header' => $this->transformHeader($this->data),

            $this->makePageKey() => $this->transformData($this->data),

            'seo' => $this->transformSeo($this->data),

            'footer' => $this->transformFooter($this->data),
        ]);
    }

    /**
     * @param null $data
     * @return array
     */
    protected function transformSeo($data = null)
    {
        return (new Seo($data ?? $this->data))->transform();
    }

    /**
     * @param $data
     * @return array
     */
    protected function transformHeader($data)
    {
        return (new Header($data ?? $this->data))->transform();
    }

    /**
     * @param $data
     * @return array
     */
    protected function transformFooter($data)
    {
        return (new Footer($data ?? $this->data))->transform();
    }

    /**
     * @param null $repositoryClass
     * @return array|\Illuminate\Support\Collection|mixed|string|void|null
     */
    public function transformBlocks($repositoryClass = null)
    {
        /// TODO: have to abstract repository name here!! &&&
        $this->repository = app($repositoryClass ?? EventRepository::class);

        $blocks = $this->organizeBlocks(null, $this->blocks); // organize root blocks

        return (new Block($blocks->values()))->transform();
    }

    /**
     * @return mixed|string
     */
    protected function makeTemplateName()
    {
        if (($this->data['type'] ?? null) === 'event') {
            return 'event-detail';
        }

        if (isset($this->data['data']['frontendTemplate'])) {
            return $this->data['data']['frontendTemplate'];
        }

        if (isset($this->data['frontendTemplate'])) {
            return $this->data['frontendTemplate'];
        }

        if (isset($this->data['type'])) {
            return $this->data['type'];
        }

        return 'home';
    }

    /**
     * @return string
     */
    public function makePageKey()
    {
        return 'page';
    }

    /**
     * @param $name
     * @return array|\Illuminate\Support\Collection|mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed|null
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->data, $name)) {
            $object = $this->data;
        } elseif (is_array($this->data) && isset($this->data['data'])) {
            $object = $this->data['data'];
        } elseif (isset($this->data['block'])) {
            $object = $this->data['block'];
        } else {
            return null;
        }

        if (
            $name === 'getMediaParams' &&
            $object instanceof \A17\Twill\Models\Block
        ) {
            return $this->mediaParamsForBlocks();
        }

        return call_user_func_array([$object, $name], $arguments);
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getMediaRawUrl($uuid)
    {
        return ImageService::getRawUrl(
            $uuid instanceof Media ? $uuid->uuid : $uuid,
        );
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset) ||
            isset($this->data->{$offset}) ||
            isset($this->data[$offset]) ||
            isset($this->data['data'][$offset]) ||
            //(isset($this['content'][$offset]) ||
            isset($this->data['content'][$offset]) ||
            isset($this->data['data'][$offset]) ||
            isset($this->data['data']['content'][$offset]) ||
            isset($this->block->{$offset}) ||
            isset($this->data['block'][$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * @param mixed $offset
     * @return array|\Illuminate\Support\Collection|mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param $name
     * @return array|\Illuminate\Support\Collection|mixed|null
     */
    public function get($name)
    {
        if ($name === 'blocks' && $this instanceof Block) {
            return $this->getBlocks();
        }

        if ($name === 'browsers' && $this instanceof Block) {
            return $this->getBrowsers();
        }

        if (is_object($this->data) && property_exists($this->data, $name)) {
            return $this->data->{$name};
        }

        if (isset($this->{$name})) {
            return $this->{$name};
        }

        if (isset($this->data->{$name})) {
            return $this->data->{$name};
        }

        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        if (isset($this->content[$name])) {
            return $this->content[$name];
        }

        if (isset($this->data['data'][$name])) {
            return $this->data['data'][$name];
        }

        if (isset($this->data['data']->$name)) {
            return $this->data['data']->$name;
        }

        if (isset($this->data['content'][$name])) {
            return $this->data['content'][$name];
        }

        if (isset($this->data['data']['content'][$name])) {
            return $this->data['data']['content'][$name];
        }

        // Developer may also refer to Twill's Block Model
        if (isset($this->block->{$name})) {
            return $this->block->{$name};
        }

        if (isset($this->data['block'][$name])) {
            return $this->data['block'][$name];
        }

        return null;
    }

    public function transformMedia($object = null, $role = null, $crop = null)
    {
        $object = $object ?? $this;

        if ($object instanceof Transformer) {
            $mediaTransformer =
                $object instanceof MediaTransformer
                    ? $object
                    : swap_class(
                        get_class($object),
                        MediaTransformer::class,
                        $object ?? $this,
                    );
        } else {
            $mediaTransformer = new MediaTransformer($object);
        }

        if (filled($role)) {
            $mediaTransformer->setCroppings($role, $crop);
        }

        return $mediaTransformer->transform();
    }
}
