<?php

declare(strict_types=1);

namespace App\MCPTools;

use App\Bundles\BundleUpdater;
use App\Bundles\SaveBundleTransaction;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\NotFoundException;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

final class SaveProjectTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "save_project";
    }
    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "objectID" => [
                    "type" => "integer",
                    "description" => "objectID of the Project to save.",
                ],
            ],
            "required" => ["objectID"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var int $objectID */
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        $request = $this->fetchRequest("Project");
        $request->predicate = $this->buildPredicate("%K = %d", new ArrayClass([ManagedObjectObjectIDKey, $objectID]));
        /** @var Project $project */
        $project = $this->context->fetch($request)->first ?? throw new NotFoundException("Project $objectID was not found");
        new SaveBundleTransaction(new BundleUpdater($project))->execute();
        $this->context->save();
        return $this->jsonResult($project->jsonSerialize());
    }
}
