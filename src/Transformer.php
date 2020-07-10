<?php

namespace A17\TwillTransformers;

use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use A17\TwillTransformers\Transformers\Block;
use A17\TwillTransformers\Behaviours\HasMedia;
use A17\TwillTransformers\Exceptions\Template;
use A17\TwillTransformers\Behaviours\HasBlocks;
use A17\TwillTransformers\Behaviours\HasConfig;
use A17\TwillTransformers\Behaviours\ClassFinder;
use A17\TwillTransformers\Behaviours\HasTranslation;
use A17\TwillTransformers\Contracts\Transformer as TransformerContract;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

abstract class Transformer implements TransformerContract, ArrayAccess
{
    use HasMedia, HasBlocks, HasTranslation, ClassFinder, HasConfig;

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

    public function __construct($data = null)
    {
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

        return $sanitized;
    }

    /**
     * @param mixed $data
     *
     * @return \A17\TwillTransformers\Transformer
     * @throws \A17\TwillTransformers\Exceptions\Transformer
     */
    public function setData($data = null)
    {
        if (blank($data)) {
            return $this;
        }

        if (filled($this->data)) {
            TransformerException::dataAlreadySet();
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
        $this->oldLocale = locale();

        $this->activeLocale = $locale;

        set_local_locale($locale);
    }

    /**
     * @return array|null
     */
    public function transform()
    {
        return null;
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
        return $this->templateName ??
            ($this->template_name ??
                ($this->get('template_name') ??
                    ($this->callMethod('templateName') ??
                        ($this->config('templates.default') ??
                            Template::notFound()))));
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
        return $this->__callTransformMethod($name, $arguments) ??
            $this->__forwardCallTo($name, $arguments);
    }

    public function __callTransformMethod($name, $arguments)
    {
        if (Str::startsWith($name, 'transform')) {
            $transformer = $this->findTransformerByMethodName($name);

            if (filled($transformer)) {
                $transformMethod = $this->getTransformMethod();

                return $this->transformerSetDataOrTransmorph(
                    $transformer,
                    $arguments[0] ?? $this,
                )->$transformMethod();
            }

            TransformerException::methodNotFound($name);
        }

        return null;
    }

    public function __forwardCallTo($name, $arguments)
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
            return (new $class())->setActiveLocale($this);
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
        return property_exists($this, $offset) ||
            isset(to_array($this->content ?? null)[$offset]) ||
            isset($this->data->{$offset}) ||
            isset($this->data[$offset]) ||
            isset($this->data['data'][$offset]) ||
            isset(to_array($this->data['content'] ?? null)[$offset]) ||
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

    public function getProperty($name)
    {
        $data = $this->data ?? ($this->block ?? null);

        if (blank($data)) {
            return null;
        }

        if ($name === 'blocks' && $this instanceof Block) {
            return $this->getBlocks();
        }

        if ($name === 'browsers' && $this instanceof Block) {
            return $this->getBrowsers();
        }

        while ($data instanceof Transformer) {
            $data = $data->getData();
        }

        if (is_object($data) && property_exists($data, $name)) {
            return $data->{$name};
        }

        if (isset($this->{$name})) {
            return $this->{$name};
        }

        if (isset($data->{$name})) {
            return $data->{$name};
        }

        if (isset($data['data']->{$name})) {
            return $data['data']->{$name};
        }

        if (isset($data[$name])) {
            return $data[$name];
        }

        if (isset($data['data'][$name])) {
            return $data['data'][$name];
        }

        if (isset($data['data']->$name)) {
            return $data['data']->$name;
        }

        if (isset(to_array($this->content ?? null)[$name])) {
            return to_array($this->content)[$name];
        }

        if (isset(to_array($data['content'] ?? null)[$name])) {
            return to_array($data['content'])[$name];
        }

        if (isset(to_array($data['data']['content'] ?? null)[$name])) {
            return to_array($data['data']['content'])[$name];
        }

        // Developer may also refer to Twill's Block Model
        if (isset($this->block->{$name})) {
            return $this->block->{$name};
        }

        if (isset($data['block'][$name])) {
            return $data['block'][$name];
        }

        return null;
    }

    public function getData()
    {
        return $this->data;
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
        return $this->activeLocale ?? locale();
    }

    protected function setActiveLocale($locale)
    {
        if (!is_string($locale)) {
            if (
                $locale instanceof Model &&
                ($modelLocale = $locale->getAttributes()['locale'] ?? null)
            ) {
                $locale = $modelLocale;
            } elseif (isset($locale['active_locale'])) {
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
            set_local_locale($this->oldLocale);
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
        static::$recurse[__CLASS__] = 0;
    }

    public function isCallingTransformRecursively()
    {
        if (!isset(static::$recurse[__CLASS__])) {
            static::$recurse[__CLASS__] = 0;
        }

        return static::$recurse[__CLASS__]++ > 2;
    }
}
