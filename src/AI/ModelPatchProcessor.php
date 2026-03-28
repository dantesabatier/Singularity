<?php

namespace App\AI;

use App\Model\Model;

abstract class ModelPatchProcessor
{
    public function __construct(public ModelPatch $patch, public readonly Model $model)
    {
        $this->process();
    }

    abstract protected function process(): void;
}
