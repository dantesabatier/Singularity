<?php

declare(strict_types=1);

namespace App\MCPTools;

use App\Model\Entity;
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

final class DesignModelTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "design_model";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "objectID" => [
                    "type" => "integer",
                    "description" => "objectID of the Project to model.",
                ],
                "description" => [
                    "type" => "string",
                    "description" => "Plain-language description of the application or domain to model.",
                ],
            ],
            "required" => ["objectID", "description"],
        ];
    }
    /** @var list<string> */
    public array $instructions {
        get => [
            "Propose a complete, well-normalised data model for the application described above.",
            "Entity names must be singular PascalCase (Order, not Orders).",
            "Attribute and relationship names must be camelCase.",
            "Always define both sides of every relationship (relationship + inverse on the destination entity).",
            "Every entity must include a creationDate attribute (type: date, isOptional: false).",
            "Do not include objectID — the framework adds it automatically.",
            "Use isAbstract: true only for entities that are never instantiated directly.",
            "Set superentity to the parent entity name for subentities, null for root entities.",
            "Prefer normalised models: avoid redundant attributes; express references as relationships.",
            "Use deleteRule cascade only when child objects have no meaning without their parent.",
            "Use deleteRule nullify for optional references; deny to block parent deletion while children exist.",
            "Do not re-propose entities already present in existingEntities unless adding new attributes or relationships to them.",
            "Valid attribute types: string, integer, float, boolean, date, uuid, uri, data.",
            "Valid delete rules: nullify, cascade, deny, noAction.",
        ];
    }
    /** @var array<string, mixed> */
    public array $outputFormat {
        get => [
            "summary" => "One-paragraph description of the proposed model and its main design decisions.",
            "entities" => [[
                "name" => "PascalCaseEntityName",
                "description" => "What this entity represents.",
                "isAbstract" => false,
                "superentity" => null,
                "attributes" => [[
                    "name" => "camelCaseName",
                    "type" => "string|integer|float|boolean|date|uuid|uri|data",
                    "isOptional" => false,
                    "description" => "What this attribute stores.",
                ]],
                "relationships" => [[
                    "name" => "camelCaseName",
                    "destination" => "DestinationEntityName",
                    "isToMany" => false,
                    "isOptional" => true,
                    "inverse" => "inverseRelationshipName",
                    "deleteRule" => "nullify|cascade|deny|noAction",
                    "description" => "What this relationship represents.",
                ]],
            ]],
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
        /** @var string $description */
        $description = $arguments["description"] ?? fatal_error("description is required");
        $request = $this->fetchRequest("Project");
        $request->predicate = $this->buildPredicate("%K = %d", new ArrayClass([ManagedObjectObjectIDKey, $objectID]));
        /** @var Project $project */
        $project = $this->context->fetch($request)->first ?? throw new NotFoundException("Project $objectID was not found");
        $existingEntityNames = $project->model?->entities->map(fn(Entity $entity): string => $entity->name)->join(", ") ?: "none";
        return $this->jsonResult([
            "description" => $description,
            "existingEntities" => $existingEntityNames,
            "instructions" => $this->instructions,
            "outputFormat" => $this->outputFormat,
        ]);
    }
}
