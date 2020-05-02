<?php

namespace A17\Transformers\Transformers\Behaviours;

use ImageService;
use A17\Twill\Models\Media;
use Illuminate\Support\Arr;
use A17\Transformers\Services\Image\Croppings;

trait HasMedia
{
    public function transformGallery($gallery)
    {
        return [
            'variation' => $this->content['variation'],

            'data' => ['items' => $this->transformImages($gallery)],
        ];
    }

    public function transformImages($medias)
    {
        return collect($medias)->map(function ($media) {
            return $this->transformImage($media);
        });
    }

    public function transformImage($object = null, $role = null, $crop = null)
    {
        return $this->generateMediaArray(
            $object instanceof Media
                ? $object
                : ($object ?? $this)->imageObject(
                    $role ?? Croppings::FREE_RATIO_DEFAULT_ROLE_NAME,
                    $crop ?? Croppings::FREE_RATIO_DEFAULT_CROP_NAME,
                ),
        );
    }

    public function generateSources($object = null)
    {
        $mediaParams = $this->mediaParams($object);

        $crops = collect($mediaParams)->map(function ($crops, $roleName) use (
            $mediaParams
        ) {
            return collect($crops)->mapWithKeys(function (
                $crop,
                $cropName
            ) use ($mediaParams, $roleName) {
                return [
                    $cropName => $this->generateMediaSourceArray(
                        $roleName,
                        $cropName,
                        $mediaParams,
                    ),
                ];
            });
        });

        if ($this->croppingsWereSelected() ?? false) {
            return $crops[$this->role][$this->crop];
        }

        return $crops;
    }

    public function generateMediaSourceArray(
        $roleName,
        $cropName,
        $mediaParams,
        $media = null
    ) {
        $media =
            $this->data instanceof Media
                ? $this->data
                : $this->imageObject($roleName, $cropName);

        return [
            'src' => $this->getUrlWithCrop($media, $roleName, $cropName),

            'ratio' => $this->calculateImageRatio(
                $mediaParams[$roleName][$cropName]['ratio'] ??
                    ($mediaParams[$roleName][$cropName][0]['ratio'] ?? null),
                $media,
            ),
        ];
    }

    protected function getUrlWithCrop($media, $roleName, $cropName)
    {
        if ($media instanceof Media) {
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

    public function renderImage($roleName, $cropName)
    {
        return $this->generateMediaSourceArray(
            $roleName,
            $cropName,
            $this->getMediaParams(),
        );
    }

    private function generateMediaArray($object)
    {
        if (blank($object->medias) && !$object->data instanceof Media) {
            return [];
        }

        return $this->getMediaArray($object, $object->medias->first());
    }

    public function mediaParams($object = null)
    {
        return $object->getMediaParams() ?? Croppings::BLOCK_EDITOR_CROPS;
    }

    public function calculateImageRatio($ratio, $image)
    {
        if ($ratio === null) {
            $image = $image ?? $this->medias->first();

            $ratio =
                ($image->pivot->crop_w ?? $image->width) /
                ($image->pivot->crop_h ?? $image->height);
        }

        return $ratio;
    }

    private function getMediaArray($object, $media)
    {
        return [
            'src' => $this->getMediaRawUrl($media),
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->getMetadata('title'),
            'caption' => $media->getMetadata('caption'),
            'alt' => $media->getMetadata('altText'),
            'sources' => $this->generateSources($object),
        ];
    }

    private function getMediaArraySource($object, $media, $roleName, $cropName)
    {
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
}
