<?php

namespace App\Responders;

use App\AI\ModelPatchBuilder;
use App\AI\PatchOperationFactory;
use App\AI\PatchRuntimeContext;
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
use Sabatier\Service\JSONDecorator;
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
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException();
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function propose(): void
    {
        $parameters = $this->request->parameters;
        $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        /** @var string $prompt */
        $prompt = $parameters["prompt"] ?? throw new BadRequestException();
        $project = $this->projectWithID($objectID);
        $model = $project->model ?? throw new NotFoundException();
        $this->data = new ModelPatchBuilder($model, $prompt)->patch;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function apply(): void
    {
        $parameters = $this->request->parameters;
        $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        /** @var Dictionary<mixed> $patch */
        $patch = $parameters["patch"] ?? throw new BadRequestException();
        $project = $this->projectWithID($objectID);
        $model = $project->model ?? throw new NotFoundException();
        /** @var ArrayClass<Dictionary<mixed>> $operations */
        $operations = $patch["operations"] ?? new ArrayClass();
        $runtimeContext = new PatchRuntimeContext($model, $this->managedObjectContext);
        $operations->forEach(fn(Dictionary $operation) => new PatchOperationFactory($operation, $runtimeContext)->operation->object);
        $this->managedObjectContext->save();
        $this->data = $model;
    }
}
