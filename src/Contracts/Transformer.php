<?php

namespace A17\TwillTransformers\Contracts;

interface Transformer
{
    public function setData($data);

    public function transform();
}
