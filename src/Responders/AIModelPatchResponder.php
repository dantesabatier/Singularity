<?php

namespace App\Responders;

use App\AI\ModelPatchBuilder;
use App\AI\PatchOperationFactory;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\NoCacheHeaderTransformer;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Responder;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

final class AIModelPatchResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    private function projectWithID(int $objectID): Project
    {
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ManagedObjectObjectIDKey), Expression::expressionForConstantValue($objectID));
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException("Project with objectID `$objectID` was not found");
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function propose(): void
    {
        $parameters = $this->request->parameters;
        $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException("`objectID` is required");
        /** @var string $prompt */
        $prompt = $parameters["prompt"] ?? throw new BadRequestException("`prompt` is required");
        $project = $this->projectWithID($objectID);
        $model = $project->model ?? throw new NotFoundException("Project model is missing");
        $this->data = new ModelPatchBuilder($model, $prompt)->patch;
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function apply(): void
    {
        $parameters = $this->request->parameters;
        $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException("`objectID` is required");
        /** @var Dictionary<mixed> $patch */
        $patch = $parameters["patch"] ?? throw new BadRequestException("`patch` is required");
        $project = $this->projectWithID($objectID);
        $model = $project->model ?? throw new NotFoundException("Project model is missing");
        /** @var ArrayClass<Dictionary<mixed>> $operations */
        $operations = $patch["operations"] ?? new ArrayClass();
        $operations->forEach(fn(Dictionary $rawOperation) => new PatchOperationFactory($rawOperation, $model)->operation->object);
        $this->managedObjectContext->save();
        $this->data = $model;
    }
}
