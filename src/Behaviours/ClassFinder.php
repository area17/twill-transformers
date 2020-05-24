<?php

namespace A17\TwillTransformers\Behaviours;

use Illuminate\Support\Str;

trait ClassFinder
{
    public function findTransformerClass($class)
    {
        $split = collect(explode('_', Str::snake($class)))->map(
            fn($part) => Str::studly($part),
        );

        $together = collect($split->all()); // clone was not working

        $shifted =
            $together->first() === 'Block' ? $together->shift() . '\\' : '';

        $together = $shifted . $together->implode('');

        $classNames = collect([$together, $split->implode('\\')]);

        $total = $split->count();

        foreach (range(1, $total - 1) as $index) {
            $classNames[] =
                collect($split->chunk($index)->first())->implode('') .
                '\\' .
                $split->skip($index)->implode('\\');

            $classNames[] =
                collect($split->chunk($index)->first())->implode('\\') .
                '\\' .
                $split->skip($index)->implode('');
        }

        return $this->getNamespaces()->reduce(function ($keep, $namespace) use (
            $classNames
        ) {
            return $keep ??
                collect($classNames)->reduce(function ($keep, $class) use ($namespace) {
                    if ($keep) {
                        return $keep;
                    }

                    $name = "{$namespace}\\{$class}";

                    if ($this->isReservedWord($name)) {
                        $name .= 'Block';
                    }

                    return class_exists($name) ? $name : null;
                });
        });
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

    public function getNamespaces()
    {
        return collect([
            $this->config('namespaces.app.transformers'),
            $this->config('namespaces.package.transformers'),
        ])->filter();
    }
}
