<?php

namespace A17\TwillTransformers\Transformers;

use A17\TwillTransformers\Transformer;
use A17\TwillTransformers\Behaviours\HasMedia;

class Media extends Transformer
{
    use HasMedia;

    protected $role;

    protected $crop;

    /**
     * @return array|null
     */
    public function transform()
    {
        return $this->generateMediaArray($this, $this->role, $this->crop);
    }

    /**
     * @param $role
     * @param $crop
     * @return $this
     */
    public function setCroppings($role, $crop)
    {
        $this->role = $role;

        $this->crop = $crop;

        return $this;
    }

    /**
     * @return bool
     */
    public function croppingsWereSelected()
    {
        return filled($this->role) || filled($this->crop);
    }
}
