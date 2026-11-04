<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace App\MCPTools;

use App\Bundles\BundleUpdater;
use App\Bundles\SaveBundleTransaction;
use App\Bundles\SubclassTransaction;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\WriteTool;
use Sabatier\Service\NotFoundException;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

final class GenerateSubclassesTool extends WriteTool
{
    #[Override]
    protected(set) string $name = "generate_subclasses";
    #[Override]
    protected(set) bool $isOpenWorld = false;
    #[Override]
    protected(set) array $inputSchema = [
        "type" => "object",
        "properties" => [
            ManagedObjectObjectIDKey => [
                "type" => "integer",
                "description" => "objectID of the Project whose entities should be generated.",
            ],
        ],
        "required" => [ManagedObjectObjectIDKey],
    ];

    #[Override]
    public ?string $authorizationResource {
        get => "Project";
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        /** @var int $objectID */
        $objectID = $this->arguments[ManagedObjectObjectIDKey] ?? fatal_error("objectID is required");
        try {
            return $this->jsonResult($this->modify($this->entity("Project"), $objectID, function (Project $project): void {
                new SubclassTransaction($project)->execute();
                new SaveBundleTransaction(new BundleUpdater($project))->execute();
            }));
        } catch (NotFoundException) {
            throw new NotFoundException("Project $objectID was not found");
        }
    }
}
