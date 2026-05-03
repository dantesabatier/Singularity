<?php

declare(strict_types=1);

namespace App\MCPTools;

use JsonException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use function Sabatier\Foundation\fatal_error;

final class DesignModelTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "design_model";
    }
    #[Override]
    public string $description {
        get => "Returns the modelling constraints, expected output format, and current model state so you can propose a complete data model for the given application description. After calling this tool, produce the proposal yourself and apply it using create.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "description" => [
                    "type" => "string",
                    "description" => "Plain-language description of the application or domain to model.",
                ],
            ],
            "required" => ["description"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $description */
        $description = $arguments["description"] ?? fatal_error("description is required");
        $schema = $this->descriptor->describe();
        $existingEntityNames = $schema->entities->keys->join(", ");
        return $this->jsonResult([
            "description" => $description,
            "existingEntities" => $existingEntityNames ?: "none",
            "instructions" => $this->instructions(),
            "outputFormat" => $this->outputFormat(),
        ]);
    }

    private function instructions(): array
    {
        return [
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

    private function outputFormat(): array
    {
        return [
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
}
