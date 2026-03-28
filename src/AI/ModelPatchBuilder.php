<?php

namespace App\AI;

use App\Model\Model;
use Sabatier\Foundation\ArrayClass;

final class ModelPatchBuilder
{
    private(set) ModelPatch $patch {
        get => $this->patch ??= $this->processorClasses->reduce(new TranslatePatchProcessor(new ModelPatch(), $this->model, $this->prompt)->patch,
            /**
             * @param ModelPatch $patch
             * @param class-string<ModelPatchProcessor> $decoratorClass
             * @return ModelPatch
             */
            fn(ModelPatch $patch, string $decoratorClass): ModelPatch => new $decoratorClass($patch, $this->model)->patch
        );
    }
    /** @var ArrayClass<class-string<ModelPatchProcessor>> */
    private ArrayClass $processorClasses {
        get => $this->processorClasses ??= new ArrayClass([NormalizePatchProcessor::class, ValidatePatchProcessor::class, ClassifyPatchProcessor::class]);
    }

    public function __construct(public Model $model, public string $prompt {
        set {
            $this->prompt = $value |> trim(...);
        }
    })
    {
    }
}
