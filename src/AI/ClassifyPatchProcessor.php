<?php

namespace App\AI;

use Override;

final class ClassifyPatchProcessor extends ModelPatchProcessor
{
    #[Override]
    protected function process(): void
    {
        if ($this->patch->operations->isEmpty) {
            $this->patch->summary = "No changes proposed";
            return;
        }
        if ($this->patch->warnings->isEmpty) {
            return;
        }
        $this->patch->summary = "{$this->patch->summary} (with warnings)";
    }
}
