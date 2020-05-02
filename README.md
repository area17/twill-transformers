# twill-transformers

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

This package allows you to create transformers to generate view data for your Twill app. It contains a base Transformer 
class and a series of traits, allowing you not only to transform model data, but also generate all blocks, from Twill's block editor and preview data.

## Install

Via Composer

``` bash
$ composer require area17/twill-transformers
```

## Reasoning

The main class of this package was extracted from the work we did for a client where we decided to use Storybook and Twig templates 
to build the front end. The idea is to free the back end developer from writing front-end code. For this to happen, the whole data
generation is automated, starting from the controller `view()` call.

## Usage

For those using the same approach (Storybook + Twig), this is what you have to do to make it all happen:

#### Create your own extension of this class:

``` php
namespace App\Transformers;

use A17\Transformers\Transformer as TwillTransformer;

abstract class Transformer extends TwillTransformer
{
}
```

#### Create your first Transformer

Note that data to be transformed is self-contained inside the transformer object. So `$this` holds everything it's responsible for transforming, and as it's usually a Laravel Model descendent, it also has access to everything we usually do with models, accessors, mutators, relationships, presenters, everything. 

``` php
namespace App\Transformers;

class Seo extends Transformer
{
    public function transform()
    {
        return [
            'title' => $this->seo_title,

            'description' => $this->seo_description,

            'urls' => $this->seo_urls,
        ];
    }
}
``` php

 

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email antonio@area17.com instead of using the issue tracker.

## Credits

- [Antonio Ribeiro][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/area17/twill-transformers.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/area17/twill-transformers/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/area17/twill-transformers.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/area17/twill-transformers.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/area17/twill-transformers.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/area17/twill-transformers
[link-travis]: https://travis-ci.org/area17/twill-transformers
[link-scrutinizer]: https://scrutinizer-ci.com/g/area17/twill-transformers/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/area17/twill-transformers
[link-downloads]: https://packagist.org/packages/area17/twill-transformers
[link-author]: https://github.com/antonioribeiro
[link-contributors]: ../../contributors
