<?php

namespace A17\TwillTransformers;

use ArrayAccess;
use App\Support\Constants;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use A17\TwillTransformers\Transformers\Block;
use A17\TwillTransformers\Behaviours\HasMedia;
use A17\TwillTransformers\Exceptions\Template;
use A17\TwillTransformers\Behaviours\HasBlocks;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Behaviours\HasLocale;
use A17\TwillTransformers\Behaviours\ClassFinder;
use A17\TwillTransformers\Behaviours\HasTranslation;
use A17\TwillTransformers\Contracts\Transformer as TransformerContract;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

/**
 * @property mixed $data
 * @method mixed transformBlocks($data = null)
 * @method mixed transformImages($data = null)
 * @method mixed transformBlockRaw($data = null)
 * @method mixed transformMedia($object = null, $role = null, $crop = null)
 */
abstract class Transformer implements TransformerContract, ArrayAccess
{
    use HasMedia, HasBlocks, HasTranslation, ClassFinder, HasConfig, HasLocale;

    const NO_DATA_GENERATED = 'NO-DATA-GENERATED';

    protected static $recurse = [];

    /**
     * @var array
     */
    private $data;

    /**
     * @var string
     */
    protected $activeLocale;

    /**
     * @var string
     */
    protected $oldLocale;

    /**
     * @var string|null
     */
    protected $pageKey;

    /**
     * @var string|null
     */
    protected $templateName;

    public function __construct($data = null)
    {
        if (is_object($data) && method_exists($data, 'getGlobalMediaParams')) {
            $this->setGlobalMediaParams($data->getGlobalMediaParams());
        }

        $this->setData($data);
    }

    function __destruct()
    {
        $this->restoreActiveLocale();

        $this->resetRecurse();
    }

    /**
     * Called after setting data to process data before giving it the the actual transformer.
     */
    protected function preProcessData()
    {
    }

    /**
     * Sanitize is supposed to be the last thing to be called after
     * the whole transformation process. So it will also set the
     * "transformed=true" property.
     *
     * @param $array
     * @return array
     */
    protected function sanitize($array)
    {
        $sanitized = convert_blanks_to_nulls(to_array($array));

        if (!isset($sanitized['transformed'])) {
            $sanitized['transformed'] = true;
        }

        return strip_tags_recursively($sanitized, '<p><a><br><strong><em><ul><li>');
    }

    /**
     * @param mixed $data
     *
     * @return \A17\TwillTransformers\Transformer
     * @throws \A17\TwillTransformers\Exceptions\Transformer
     */
    public function setData($data = null, $force = false)
    {
        if (blank($data)) {
            return $this;
        }

        $this->inferTemplateName($data);

        if (filled($this->data) && !$force) {
            TransformerException::dataAlreadySet(get_class($this));
        }

        $this->data =
            $data instanceof Block ? $data->data ?? $data->block : $data;

        $this->setActiveLocale($data);

        $this->setBlockType($data);

        $this->preProcessData();

        return $this;
    }

    /**
     * @param string|null $locale
     */
    protected function saveActiveLocale(?string $locale): void
    {
        $this->oldLocale = $this->locale();

        $this->activeLocale = $locale;

        $this->setLocalLocale($locale);
    }

    /**
     * @param array|Collection|null $data
     * @return array|null
     */
    public function transform()
    {
        return $this->sanitize($this->transformData());
    }

    /**
     * @return mixed|string
     */
    protected function makeTemplateName()
    {
        return $this->getTemplate();
    }

    /**
     * @return mixed|string
     */
    protected function getTemplate()
    {
        if (property_exists($this, 'templateName')) {
            return $this->templateName;
        }

        if (property_exists($this, 'template_name')) {
            return $this->template_name;
        }

        return $this->get('template_name') ??
            ($this->callMethod('templateName') ??
                ($this->config('templates.default') ?? Template::notFound()));
    }

    /**
     * @return string
     */
    public function makePageKey()
    {
        return $this->pageKey ?? 'page';
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
        $transformed = $this->__callTransformMethod($name, $arguments);

        if ($transformed === self::NO_DATA_GENERATED) {
            return null;
        }

        if (isset($transformed)) {
            return $transformed;
        }

        return $this->__forwardCallTo($name, $arguments);
    }

    public function __callTransformMethod($name, $arguments)
    {
        if (Str::startsWith($name, 'transform')) {
            $transformer = $this->findTransformerByMethodName($name);

            if (filled($transformer)) {
                $transformMethod = $this->getTransformMethod();

                static::incrementTransformCalls();

                $transformed = $this->transformerSetDataOrTransmorph(
                    $transformer,
                    $arguments[0] ?? $this,
                )->$transformMethod();

                static::dencrementTransformCalls();

                return $transformed ?? self::NO_DATA_GENERATED;
            }

            TransformerException::methodNotFound($name);
        }

        return null;
    }

    public function __forwardCallTo($name, $arguments)
    {
        if (
            filled($this->data ?? null) &&
            is_object($this->data) &&
            method_exists($this->data, $name)
        ) {
            $object = $this->data;
        } elseif (
            filled($this->data ?? null) &&
            is_object($this->data) &&
            !method_exists($this->data, $name) &&
            !$this->data instanceof Transformer
        ) {
            $object = $this->data;
        } elseif (
            filled($this->data['data'] ?? null) &&
            is_object($this->data['data']) &&
            method_exists($this->data['data'], $name)
        ) {
            $object = $this->data['data'];
        } elseif (
            isset($this->data) &&
            is_array($this->data) &&
            isset($this->data['data'])
        ) {
            $object = $this->data['data'];
        } elseif (isset($this->data['block'])) {
            $object = $this->data['block'];
        } else {
            return null;
        }

        if (
            $name === 'getMediasParams' &&
            $object instanceof \A17\Twill\Models\Block
        ) {
            return $this->mediasParamsForBlocks();
        }

        if (!is_object($object)) {
            TransformerException::methodNotFound($name);
        }

        return call_user_func_array([$object, $name], $arguments);
    }

    /**
     * @param $methodName
     * @return \A17\TwillTransformers\Transformer|null
     */
    public function findTransformerByMethodName($methodName)
    {
        if (!Str::startsWith($methodName, 'transform')) {
            return null;
        }

        $class = $this->findTransformerClass(
            Str::after($methodName, 'transform'),
        );

        if (filled($class)) {
            return (new $class())
                ->setActiveLocale($this)
                ->setGlobalMediaParams($this->getGlobalMediaParams());
        }

        return null;
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
        $contentArray = $this->getBlockContents($this);

        return property_exists($this, $offset) ||
            (is_array($contentArray) && isset($contentArray[$offset])) ||
            isset($this->data->{$offset}) ||
            isset($this->getBlock()->$offset) ||
            (is_iterable($this->data) &&
                (isset($this->data[$offset]) ||
                    isset($this->data['data'][$offset]) ||
                    isset($this->getBlockContents($this->data)[$offset]) ||
                    isset($this->data['data'][$offset]) ||
                    isset(
                        $this->getBlockContents($this->data['data'] ?? null)[$offset],
                    ) ||
                    isset($this->getBlock($this->data)[$offset])));
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
     * This method is responsible for retrieving properties from the current Transformer or
     * any the data object (Model? Block?) stored on it.
     *
     * @param $name
     * @return array|\Illuminate\Support\Collection|mixed|null
     */
    public function get($name)
    {
        if (filled($value = $this->getProperty($name))) {
            $value = is_array($value) ? collect($value) : $value;
        }

        return $value;
    }

    public function getProperty($name, $data = null)
    {
        $data ??= $this->data ?? ($this->block ?? null);

        if (blank($data)) {
            return null;
        }

        if ($name === 'blocks' && $this instanceof Block) {
            return $this->getBlocks();
        }

        if ($name === 'browsers' && $this instanceof Block) {
            return $this->getBrowsers();
        }

        if ($name === 'data' && is_traversable($data) && isset($data['data'])) {
            return $data['data'];
        }

        if (
            $name === 'data' &&
            ($data instanceof Model ||
                (is_object($data) && !$data instanceof Transformer) ||
                is_traversable($data))
        ) {
            return $data;
        }

        while ($data instanceof Transformer) {
            $data = $data->getData();
        }

        if (is_object($data) && property_exists($data, $name)) {
            return $data->{$name};
        }

        if (isset($data->{$name})) {
            return $data->{$name};
        }

        if (isset($data->{$name})) {
            return $data->{$name};
        }

        if (is_iterable($data)) {
            if (isset($data[$name])) {
                return $data[$name];
            }

            if (filled($data['data']->{$name} ?? null)) {
                return $data['data']->{$name};
            }

            if (filled($data['data'][$name] ?? null)) {
                return $data['data'][$name];
            }

            if (isset($data['data']->$name)) {
                return $data['data']->$name;
            }
        }

        if (
            isset($data->content) &&
            isset(to_array($data->content ?? null)[$name])
        ) {
            return to_array($data->content)[$name];
        }

        if (is_iterable($data)) {
            if (isset($this->getBlockContents($data)[$name])) {
                return $this->getBlockContents($data)[$name];
            }

            if (isset($this->getBlockContents($data['data'] ?? null)[$name])) {
                return $this->getBlockContents($data['data'] ?? null)[$name];
            }
        }

        // Developer may also refer to Twill's Block Model
        if (isset($data->block) && isset($data->block->{$name})) {
            return $data->block->{$name};
        }

        if (is_iterable($data) && isset($data['block'][$name])) {
            return $data['block'][$name];
        }

        return null;
    }

    public function getData()
    {
        $propertyData = $this->getProperty('data');

        $data =
            is_null($propertyData) && $this->isBlock($this)
                ? $this
                : $propertyData;

        $this->absorbBlockInternalData($this, $data);

        return is_array($data) ? collect($data) : $data;
    }

    public function absorbBlockInternalData($block, &$data)
    {
        if ($this->isBlock($block)) {
            foreach ($block->getInternalVars() as $key => $content) {
                if (blank($data[$key] ?? null)) {
                    $data[$key] = $content;
                }
            }
        }
    }

    public function getTransformMethod()
    {
        return $this->isCallingTransformRecursively()
            ? 'transformData'
            : 'transform';
    }

    public function transformerSetDataOrTransmorph($transformer, $data)
    {
        if ($data === $this) {
            $data = $this->getData();
        }

        if ($transformer instanceof Block && $data instanceof Block) {
            return swap_class(get_class($data), get_class($transformer), $data);
        }

        return $transformer->setData($data);
    }

    public function getActiveLocale()
    {
        return $this->activeLocale ?? $this->locale();
    }

    protected function setActiveLocale($locale)
    {
        if (!is_string($locale)) {
            if (
                $locale instanceof Model &&
                ($modelLocale = $locale->getAttributes()['locale'] ?? null)
            ) {
                $locale = $modelLocale;
            } elseif (is_iterable($locale) && isset($locale['active_locale'])) {
                $locale = $locale['active_locale'];
            } elseif ($locale instanceof Transformer) {
                $locale = $locale->getActiveLocale();
            } else {
                $locale = $this->getActiveLocale() ?? null;
            }
        }

        $this->saveActiveLocale($locale);

        return $this;
    }

    protected function restoreActiveLocale()
    {
        if (isset($this->oldLocale)) {
            $this->setLocalLocale($this->oldLocale);
        }
    }

    protected function setBlockType($data)
    {
        /// implemented on Transformer Block class
    }

    public function callMethod($name, $parameters = [])
    {
        try {
            return call_user_func_array([$this, $name], $parameters);
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function resetRecurse()
    {
        static::$recurse[get_class($this)] = 0;
    }

    public function incrementTransformCalls()
    {
        if (!isset(static::$recurse[get_class($this)])) {
            static::$recurse[get_class($this)] = 0;
        }

        static::$recurse[get_class($this)]++;
    }

    public function dencrementTransformCalls()
    {
        if (!isset(static::$recurse[get_class($this)])) {
            static::$recurse[get_class($this)] = 0;
        }

        static::$recurse[get_class($this)]--;
    }

    public function isCallingTransformRecursively()
    {
        return (static::$recurse[get_class($this)] ?? 0) > 50;
    }

    public function set($property, $value)
    {
        if (is_object($this->data)) {
            $this->data->{$property} = $value;
        }

        if (is_traversable($this->data)) {
            $this->data[$property] = $value;
        }
    }

    public function getItem($name, $from = null)
    {
        $from = $from ?? $this->data;

        if (property_exists($this, $name)) {
            return $this->content;
        }

        $value = $value ?? (isset($from[$name]) ? $from[$name] : []);

        return to_array($value);
    }

    public function getBlockContents($from = null)
    {
        $from = $from ?? $this->data;

        return $this->getItem('content');
    }

    public function getBlock($from = null)
    {
        $from = $from ?? $this->data;

        return $this->getItem('block');
    }

    public function transformData(array|null $data = null): array|Collection
    {
        return $data ?? $this->getData();
    }

    /**
     * @throws \A17\TwillTransformers\Exceptions\Transformer
     */
    public function __invoke(array|null $data = null): array|Collection
    {
        return $this->transform($data);
    }

    public function inferTemplateName($data): void
    {
        if (is_array($data) && filled($templateName = $data['template_name'] ?? $data['templateName'] ?? null)) {
            $this->templateName = $templateName;
        }
    }
}
