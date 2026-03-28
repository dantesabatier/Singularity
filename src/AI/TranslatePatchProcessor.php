<?php

namespace App\AI;

use App\Model\Model;
use Override;

final class TranslatePatchProcessor extends ModelPatchProcessor
{
    public function __construct(ModelPatch $patch, Model $model, public string $prompt {
        set {
            $this->prompt = $value |> trim(...);
        }
    })
    {
        parent::__construct($patch, $model);
    }

    #[Override]
    protected function process(): void
    {
    }
}
