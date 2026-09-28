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

    #[Override]
    public function authorizationResource(Dictionary $arguments): string
    {
        return "Project";
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        /** @var int $objectID */
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        try {
            return $this->jsonResult($this->modify("Project", $objectID, function (Project $project): void {
                new SaveBundleTransaction(new BundleUpdater($project))->execute();
            }));
        } catch (NotFoundException) {
            throw new NotFoundException("Project $objectID was not found");
        }
    }
}
