<?php

namespace A17\TwillTransformers\Behaviours;

use ImageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Transformers\Transformer;
use A17\Twill\Models\Media as MediaModel;
use A17\TwillTransformers\Support\Croppings;
use A17\TwillTransformers\Transformers\Media as MediaTransformer;

trait HasMedia
{
    /**
     * @param $object
     * @return mixed
     */
    protected function getFirstTranslatedMedia($object)
    {
        return $object->medias->first(function ($media) {
            return $media->pivot->locale === locale();
        }) ?? $object->medias->first();
    }

    /**
     * @param null $object
     * @param null $role
     * @param null $crop
     * @return array
     */
    public function transformImage($object = null, $role = null, $crop = null)
    {
        $role ??= $object->pivot->role;
        $crop ??= $object->pivot->crop;

        return $this->generateMediaArray(
            $object instanceof MediaModel
                ? $object
                : ($object ?? $this)->imageObject($role, $crop),
            $role,
            $crop,
        );
    }

    /**
     * @param null $object
     * @param null $role
     * @param null $crop
     * @return array|null
     */
    public function transformMedia($object = null, $role = null, $crop = null)
    {
        $object ??= $this;

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

    /**
     * @param $uuid
     * @return mixed
     */
    public function getMediaRawUrl($uuid)
    {
        return ImageService::getRawUrl(
            $uuid instanceof MediaModel ? $uuid->uuid : $uuid,
        );
    }

    /**
     * @param null $object
     * @return \Illuminate\Support\Collection
     */
    public function generateSources(
        $object = null,
        $filterRole = null,
        $filterCrop = null
    ) {
        $mediaParams = $this->mediaParams($object);

        $crops = collect($mediaParams)->map(function ($crops, $roleName) use (
            $mediaParams,
            $object,
            $filterRole,
            $filterCrop
        ) {
            return collect($crops)->mapWithKeys(function (
                $crop,
                $cropName
            ) use ($mediaParams, $roleName, $object, $filterRole, $filterCrop) {
                if (filled($filterRole)) {
                    if (
                        $roleName !== $filterRole ||
                        $cropName !== $filterCrop
                    ) {
                        return [$cropName => null];
                    }
                }

                return [
                    $cropName => $this->generateMediaSourceArray(
                        $roleName,
                        $cropName,
                        $mediaParams,
                        $object instanceof MediaModel ? $object : null,
                    ),
                ];
            });
        });

        if ($this->croppingsWereSelected() ?? false) {
            return $crops
                ->map(function ($role, $roleName) {
                    return $role->filter(
                        fn($crop, $cropName) => $cropName == $this->crop,
                    );
                })
                ->filter(fn($role, $roleName) => $roleName == $this->role);
        }

        return $crops;
    }

    /**
     * @param $roleName
     * @param $cropName
     * @param $mediaParams
     * @param null $media
     * @return array
     */
    public function generateMediaSourceArray(
        $roleName,
        $cropName,
        $mediaParams,
        $media = null
    ) {
        $media ??=
            $this->data instanceof MediaModel
                ? $this->data
                : $this->imageObject($roleName, $cropName);

        return [
            'src' => ($src = $this->getUrlWithCrop(
                $media,
                $roleName,
                $cropName,
            )),

            'ratio' => $this->calculateImageRatio(
                $mediaParams[$roleName][$cropName]['ratio'] ??
                    ($mediaParams[$roleName][$cropName][0]['ratio'] ?? null),
                $media,
            ),
        ] +
            $this->makeExtraParams(
                $src,
                $mediaParams[$roleName][$cropName]['extra'] ??
                    ($mediaParams[$roleName][$cropName][0]['extra'] ?? []),
            );
    }

    /**
     * @param $media
     * @param $roleName
     * @param $cropName
     * @return mixed
     */
    protected function getUrlWithCrop($media, $roleName, $cropName)
    {
        if ($media instanceof MediaModel) {
            return ImageService::getUrlWithCrop(
                $media->uuid,
                Arr::only($media->pivot->toArray(), [
                    'crop_x',
                    'crop_y',
                    'crop_w',
                    'crop_h',
                ]),
                [],
            );
        }

        return $this->image($roleName, $cropName, [], false, false, $media);
    }

    /**
     * @param $roleName
     * @param $cropName
     * @return array
     */
    public function renderImage($roleName, $cropName)
    {
        return $this->generateMediaSourceArray(
            $roleName,
            $cropName,
            $this->getMediaParams(),
        );
    }

    /**
     * @param $object
     * @return array
     */
    protected function generateMediaArray($object, $role = null, $crop = null)
    {
        $media = $this->getFirstMedia($object);

        if (blank($media)) {
            return [];
        }

        return $this->getMediaArray($object, $media, $role, $crop);
    }

    protected function getFirstMedia($object = null)
    {
        $object ??= $this;

        if ($object instanceof MediaModel) {
            return $object;
        }

        if (($object->data ?? null) instanceof MediaModel) {
            return $object->data;
        }

        if (filled($object->medias ?? null)) {
            if ($this->croppingsWereSelected()) {
                $media = $this->imageObject($this->role, $this->crop);

                if (filled($media)) {
                    return $media;
                }
            }

            return $this->getFirstTranslatedMedia($object);
        }

        return null;
    }

    /**
     * @param null $object
     * @return mixed
     */
    public function mediaParams($object = null)
    {
        if (isset($object->mediaParams)) {
            return $object->mediaParams;
        }

        $mediaParams =
            blank($object) || $object instanceof MediaModel
                ? $this->getMediaParams()
                : $object->getMediaParams() ??
                    $this->extractMediaParamsFromModel($object);

        return $mediaParams ?? Croppings::BLOCK_EDITOR;
    }

    /**
     * @param $ratio
     * @param $image
     * @return float|int
     */
    public function calculateImageRatio($ratio, $image)
    {
        if ($ratio === null) {
            $image ??= $this->getFirstTranslatedMedia($this);

            $ratio =
                ($image->pivot->crop_w ?? $image->width) /
                ($image->pivot->crop_h ?? $image->height);
        }

        return $ratio;
    }

    /**
     * @param $object
     * @param $media
     * @return array
     */
    protected function getMediaArray(
        $object,
        $media,
        $role = null,
        $crop = null
    ) {
        return [
            'src' => $this->getMediaRawUrl($media),
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->getMetadata('title'),
            'caption' => $media->getMetadata('caption'),
            'alt' => $media->getMetadata('altText'),
            'sources' => $this->generateSources($object, $role, $crop),
        ];
    }

    /**
     * @param $object
     * @param $media
     * @param $roleName
     * @param $cropName
     * @return array
     */
    protected function getMediaArraySource(
        $object,
        $media,
        $roleName,
        $cropName
    ) {
        $mediaParams = $this->mediaParams($object);

        return [
            'src' => $this->getMediaRawUrl($media),
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->getMetadata('title'),
            'caption' => $media->getMetadata('caption'),
            'alt' => $media->getMetadata('altText'),
            'sources' => [
                $roleName => [
                    $cropName => $this->generateMediaSourceArray(
                        $roleName,
                        $cropName,
                        $mediaParams,
                        $media,
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function mediaParamsForBlocks()
    {
        return Croppings::BLOCK_EDITOR;
    }

    public function extractMediaParamsFromModel($object)
    {
        if (isset($object['blockable_type'])) {
            $model = new $object['blockable_type']();

            if (filled($params = $model->mediasParams ?? null)) {
                return $params;
            }
        }

        return null;
    }

    public function makeExtraParams($src, $extraParams)
    {
        return collect($extraParams)
            ->map(
                fn($definitions) => $this->makeExtraParamsString(
                    $definitions,
                    $src,
                ),
            )
            ->map(function ($param, $key) use ($extraParams, $src) {
                $isSpecial = isset($extraParams[$key][0]['__items']);

                return $isSpecial
                    ? $param
                    : $this->addParamsToUrl($src, $param);
            })
            ->toArray();
    }

    public function makeExtraParamsString($definitions, $src = null)
    {
        return collect($definitions)
            ->map(
                fn($definition, $key) => $this->buildParamDefinition(
                    $definition,
                    $key,
                    $src,
                ),
            )
            ->values()
            ->implode('&');
    }

    public function buildParamDefinition($definition, $key, $src)
    {
        if (isset($definition['__items'])) {
            return $this->buildParamDefinitionSpecial($definition, $src);
        }

        return "$key=$definition";
    }

    public function addParamsToUrl($src, $params)
    {
        $glue = Str::contains($src, '?') ? '&' : '?';

        return "{$src}{$glue}{$params}";
    }

    public function buildParamDefinitionSpecial($definition, $src)
    {
        $glue = $definition['__glue'] ?? '&';

        return collect($definition['__items'])
            ->map(
                fn($definitions) => $this->makeExtraParamsString($definitions),
            )
            ->map(fn($string) => $this->addParamsToUrl($src, $string))
            ->implode($glue);
    }
}
