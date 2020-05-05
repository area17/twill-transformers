<?php

namespace A17\TwillTransformers;

use ArrayAccess;
use Illuminate\Support\Str;
use A17\TwillTransformers\Transformers\Block;
use A17\TwillTransformers\Behaviours\HasMedia;
use A17\TwillTransformers\Behaviours\HasBlocks;
use A17\TwillTransformers\Behaviours\HasTranslation;
use A17\TwillTransformers\Contracts\Transformer as TransformerContract;
use A17\TwillTransformers\Exceptions\Transformer as TransformerException;

abstract class Transformer implements TransformerContract, ArrayAccess
{
    use HasMedia, HasBlocks, HasTranslation;

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

    function __destruct() {
        $this->removeActiveLocale();
    }

    /**
     * Called after setting data to process data before giving it the the actual transformer.
     */
    protected function preProcessData()
    {
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

        $this->preProcessData();

        return $this;
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
        if (($this->data['type'] ?? null) === 'event') {
            return 'event-detail';
        }

        if (isset($this->data['data']['template_name'])) {
            return $this->data['data']['template_name'];
        }

        if (isset($this->data['template_name'])) {
            return $this->data['template_name'];
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
                return $this->transformerSetDataOrTransmorph(
                    $transformer,
                    $arguments[0] ?? $this,
                )->transform();
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

        $class = $this->findClass(Str::after($methodName, 'transform'));

        if (filled($class)) {
            return (new $class())->setActiveLocale($this);
        }

        return null;
    }

    public function findClass($class)
    {
        $split = collect(explode('_', Str::snake($class)))->map(
            fn($part) => Str::studly($part),
        );

        $togheter = collect($split->all()); // clone was not working

        $shifted =
            $togheter->first() === 'Block' ? $togheter->shift() . '\\' : '';

        $togheter = $shifted . $togheter->implode('');

        $split = $split->implode('\\');

        if ($this->isReservedWord($split)) {
            $split .= 'Block';
        }

        if ($this->isReservedWord($togheter)) {
            $togheter .= 'Block';
        }

        return $this->getNamespaces()->reduce(function ($keep, $namespace) use (
            $split,
            $togheter
        ) {
            $splitName = "{$namespace}\\{$split}";
            $togheterName = "{$namespace}\\{$togheter}";

            return $keep ??
                ((class_exists($togheterName) ? $togheterName : null) ??
                    (class_exists($splitName) ? $splitName : null));
        });
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
            isset($this->content[$offset]) ||
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

        if (isset($this->data['data']->{$name})) {
            return $this->data['data']->{$name};
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

    public function config($path)
    {
        return config("twill-transformers.{$path}");
    }

    public function getNamespaces()
    {
        return collect([
            $this->config('namespaces.app.transformers'),
            $this->config('namespaces.package.transformers'),
        ])->filter();
    }

    public function isReservedWord($word)
    {
        return collect(
            $keywords = [
                'abstract',
                'and',
                'array',
                'as',
                'break',
                'callable',
                'case',
                'catch',
                'class',
                'clone',
                'const',
                'continue',
                'declare',
                'default',
                'die',
                'do',
                'echo',
                'else',
                'elseif',
                'empty',
                'enddeclare',
                'endfor',
                'endforeach',
                'endif',
                'endswitch',
                'endwhile',
                'eval',
                'exit',
                'extends',
                'final',
                'for',
                'foreach',
                'function',
                'global',
                'goto',
                'if',
                'implements',
                'include',
                'include_once',
                'instanceof',
                'insteadof',
                'interface',
                'isset',
                'list',
                'namespace',
                'new',
                'or',
                'print',
                'private',
                'protected',
                'public',
                'require',
                'require_once',
                'return',
                'static',
                'switch',
                'throw',
                'trait',
                'try',
                'unset',
                'use',
                'var',
                'while',
                'xor',
            ],
        )->contains(Str::lower($word));
    }

    public function getData()
    {
        return $this->data;
    }

    public function transformerSetDataOrTransmorph($transformer, $data)
    {
        if ($transformer instanceof Block && $data instanceof Block) {
            return swap_class(get_class($data), get_class($transformer), $data);
        }

        return $transformer->setData($data);
    }

    public function getActiveLocale()
    {
        return $this->activeLocale ?? fallback_locale();
    }

    protected function setActiveLocale($locale)
    {
        if (!is_string($locale)) {
            if (isset($locale['active_locale'])) {
                $locale = $locale['active_locale'];
            } elseif ($locale instanceof Transformer) {
                $locale = $locale->getActiveLocale();
            } else {
                $locale = $this->getActiveLocale() ?? null;
            }
        }

        $this->oldLocale = locale();

        $this->activeLocale = $locale;

        set_local_locale($locale);

        return $this;
    }

    protected function removeActiveLocale()
    {
        if (isset($this->oldLocale)) {
            set_local_locale($this->oldLocale);
        }
    }
}
