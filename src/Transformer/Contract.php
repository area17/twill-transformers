<?php

namespace A17\Transformers\Transformers;

interface Contract
{
    public function setData($data);

    public function transform();
}
