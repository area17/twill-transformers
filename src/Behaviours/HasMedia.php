<?php

namespace A17\TwillTransformers\Behaviours;

use ImageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Transformers\Transformer;
use A17\Twill\Models\Media as MediaModel;
use A17\TwillTransformers\Support\Croppings;
use Illuminate\Database\Eloquent\Relations\Relation;
use A17\TwillTransformers\Transformers\Media as MediaTransformer;

trait HasMedia
{
    use HasLocale;

    protected $globalMediaParams;

    /**
     * @param $object
     * @return mixed
     */
    protected function getFirstTranslatedMedia($object)
    {
        return $object->medias->first(function ($media) {
            return $media->pivot->locale === locale() &&
                $media->pivot->role === $this->role &&
                $media->pivot->crop === $this->crop;
        }) ??
            $object->medias
                ->where('role', $object->role)
                ->where('role', $object->crop)
                ->first();
    }

    /**
     * @param object|null $object
     * @param string|null $role
     * @param string|null $crop
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
        $mediasParams = $this->mediasParams($object);

        $crops = collect($mediasParams)
            ->map(function ($crops, $roleName) use (
                $mediasParams,
                $object,
                $filterRole,
                $filterCrop
            ) {
                return collect($crops)
                    ->mapWithKeys(function ($crop, $cropName) use (
                        $mediasParams,
                        $roleName,
                        $object,
                        $filterRole,
                        $filterCrop
                    ) {
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
                                $mediasParams,
                                $object instanceof MediaModel ? $object : null,
                            ),
                        ];
                    })
                    ->filter();
            })
            ->filter(fn($ratios) => filled($ratios));

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
     * @param $mediasParams
     * @param null $media
     * @return array
     */
    public function generateMediaSourceArray(
        $roleName,
        $cropName,
        $mediasParams,
        $media = null
    ) {
        $media ??=
            $this->data instanceof MediaModel
                ? $this->data
                : $this->imageObject($roleName, $cropName);

        $src = $src = $this->getUrlWithCrop($media, $roleName, $cropName);

        if ($this->isEmptyImageSource($src)) {
            return null;
        }

        foreach ($mediasParams[$roleName][$cropName] ?? [] as $param) {
            if ($param['name'] == $media->pivot->ratio) {
                $ratio = $param['ratio'];
                break;
            }
        }

        return collect([
            'src' => $src,

            'crop_x' => $media->pivot->crop_x,

            'crop_y' => $media->pivot->crop_y,

            'crop_w' => $media->pivot->crop_w,

            'crop_h' => $media->pivot->crop_h,

            'lqip' => $media->pivot->lqip_data ?? '',

            'ratio' => $this->calculateImageRatio(
                $ratio ??
                    ($mediasParams[$roleName][$cropName]['ratio'] ??
                        ($mediasParams[$roleName][$cropName][0]['ratio'] ??
                            null)),
                $media,
            ),
        ])
            ->filter(fn($item) => !blank($item))
            ->merge(
                $this->makeExtraParams(
                    $src,
                    $medisaParams[$roleName][$cropName]['extra'] ??
                        ($mediasParams[$roleName][$cropName][0]['extra'] ?? []),
                ),
            )
            ->toArray();
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
            $this->getMediasParams(),
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
                return $this->imageObject($this->role, $this->crop);
            }

            return $this->getFirstTranslatedMedia($object);
        }

        return null;
    }

    /**
     * @param null $object
     * @return mixed
     */
    public function mediasParams($object = null)
    {
        if (filled($object->mediasParams ?? null)) {
            return $object->mediasParams;
        }

        if (filled($this->mediasParams ?? null)) {
            return $this->mediasParams;
        }

        $mediasParams =
            blank($object) || $object instanceof MediaModel
                ? $this->getMediasParams()
                : $object->getMediasParams() ??
                    $this->extractMediasParamsFromModel($object);

        return $mediasParams ??
            ($this->globalMediaParams ?? Croppings::BLOCK_EDITOR);
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
            'locale' => $media->pivot->locale ?? $this->locale(),
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
        $mediasParams = $this->mediasParams($object);

        $crops = $this->generateMediaSourceArray(
            $roleName,
            $cropName,
            $mediasParams,
            $media,
        );

        $sources = filled($crops)
            ? [
                $roleName => [
                    $cropName => $crops,
                ],
            ]
            : null;

        return [
            'src' => $this->getMediaRawUrl($media),
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->getMetadata('title'),
            'caption' => $media->getMetadata('caption'),
            'alt' => $media->getMetadata('altText'),
            'sources' => $sources,
        ];
    }

    /**
     * @return array
     */
    public function mediasParamsForBlocks()
    {
        return Croppings::BLOCK_EDITOR;
    }

    public function extractMediasParamsFromModel($object)
    {
        if (isset($object['blockable_type'])) {
            $class = $this->getBlockableTypeClass($object);

            $model = new $class();

            if (filled($params = $model->mediasParams ?? null)) {
                return $params;
            }
        }

        return null;
    }

    public function makeExtraParams($src, $extraParams)
    {
        return collect($extraParams)
            ->map(function ($definitions) use ($src) {
                if (is_string($definitions)) {
                    return ['type' => 'string', 'value' => $definitions];
                }

                return [
                    'type' => 'array',
                    'value' => $this->makeExtraParamsString($definitions, $src),
                ];
            })
            ->map(function ($param, $key) use ($extraParams, $src) {
                if ($param['type'] === 'string') {
                    return $param['value'];
                }

                $isSpecial = isset($extraParams[$key][0]['__items']);

                return $isSpecial
                    ? $param['value']
                    : $this->addParamsToUrl($src, $param['value']);
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

    public function isEmptyImageSource($src)
    {
        return $src ==
            'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    }

    public function getBlockableTypeClass($object)
    {
        $class = $object['blockable_type'];

        if (!class_exists($class)) {
            return Relation::getMorphedModel($class);
        }

        return $class;
    }

    public function setGlobalMediaParams($params)
    {
        $this->globalMediaParams = $params;

        return $this;
    }

    public function getGlobalMediaParams()
    {
        return $this->globalMediaParams;
    }
}
