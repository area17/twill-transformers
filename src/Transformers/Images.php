<?php

namespace A17\TwillTransformers\Transformers;

use App\Services\Image\Croppings;

class Images extends Media
{
    /**
     * @return array|null
     */
    public function transform()
    {
        $medias = $this->medias ?? [];

        $role = $this->data['role'] ?? ($this->role ?? null);

        $crop = $this->data['crop'] ?? ($this->crop ?? null);

        return $this->mergeCrops(
            $this->filterMediasByRoleAndCrop(
                $this->addMediasParamsToImages($medias),
                $role,
                $crop,
            )->map(function ($media) use ($role, $crop) {
                $a = $this->transformImage($media, $role, $crop); // TODO: REMOVE THIS

                return $this->transformImage($media, $role, $crop);
            }),
        );
    }

    public function filterMediasByRoleAndCrop($medias, $role, $crop)
    {
        $medias = collect($medias);

        if (blank($role) && blank($crop)) {
            return $medias;
        }

        return $medias->filter(function ($media) use ($role, $crop) {
            return blank($media->pivot ?? null)
                ? false
                : (blank($role) || $media->pivot->role === $role) &&
                        (blank($crop) || $media->pivot->crop === $crop);
        });
    }

    public function addMediasParamsToImages($medias)
    {
        return collect($medias)->map(function ($media) {
            $media->mediasParams = $this->mediasParams;

            return $media;
        });
    }

    public function mergeCrops($images)
    {
        $result = [];

        $images = array_remove_nulls($images);

        foreach ($images as $image) {
            if (blank($result[$image['src']] ?? null)) {
                $result[$image['src']] = $image;

                continue;
            }

            $result[$image['src']]['sources'] = array_merge_recursive(
                $result[$image['src']]['sources'],
                $image['sources'],
            );
        }

        return collect($result)->values();
    }
}
