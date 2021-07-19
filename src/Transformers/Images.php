<?php

namespace A17\TwillTransformers\Transformers;

use App\Services\Image\Croppings;
use A17\TwillTransformers\Behaviours\HasLocale;

class Images extends Media
{
    use HasLocale;

    /**
     * @return array|null
     */
    public function transform()
    {
        $medias = $this->medias ?? [];

        $role = $this->data['role'] ?? ($this->role ?? null);

        $crop = $this->data['crop'] ?? ($this->crop ?? null);

        $images = $this->mergeCrops(
            $this->filterMediasByRoleAndCrop(
                $this->addMediasParamsToImages($medias),
                $role,
                $crop,
            )->map(function ($media) use ($role, $crop) {
                return $this->transformImage($media, $role, $crop);
            }),
        );

        if ($images->pluck('locale')->contains($this->locale())) {
            return $images->where('locale', $this->locale());
        }

        return $images;
    }

    public function filterMediasByRoleAndCrop($medias, $role, $crop)
    {
        $medias = collect($medias);

        if (blank($role) && blank($crop)) {
            return $medias;
        }

        $medias = $medias->filter(function ($media) use ($role, $crop) {
            return blank($media->pivot ?? null)
                ? false
                : (blank($role) || $media->pivot->role === $role) &&
                        (blank($crop) || $media->pivot->crop === $crop);
        });

        return $this->unique($medias);
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
            $key = $image['src'].$image['locale'];

            if (blank($result[$key] ?? null)) {
                $result[$key] = $image;

                continue;
            }

            $result[$key]['sources'] = array_merge_recursive(
                $result[$key]['sources'] ?? [],
                $image['sources'] ?? [],
            );
        }

        return collect($result)->values();
    }

    protected function unique($medias)
    {
        return $medias
            ->sortBy(fn($media) => $media->pivot->locale === $this->locale() ? 0 : 1)
            ->unique(
                fn($media) => $media->pivot->media_id .
                    $media->pivot->mediable_type .
                    $media->pivot->crop .
                    $media->pivot->role,
            );
    }
}
