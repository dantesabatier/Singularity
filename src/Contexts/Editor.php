<?php

namespace App\Contexts;

use App\Model\Configuration;
use App\Model\Entity;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Project;
use App\Model\Property;
use App\Model\UniquenessConstraint;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;

readonly class Editor
{
    /**
     * @param string $title
     * @param ArrayClass<Project> $projects
     * @param Project|null $project
     * @param ArrayClass<Entity> $allEntities
     * @param ArrayClass<Entity> $rootEntities
     * @param ArrayClass<FetchRequestTemplate> $fetchRequestTemplates
     * @param ArrayClass<Configuration> $configurations
     * @param Entity|null $selectedEntity
     * @param Property|null $selectedProperty
     * @param FetchIndex|null $selectedIndex
     * @param FetchIndexElement|null $selectedIndexElement
     * @param UniquenessConstraint|null $selectedUniquenessConstraint
     * @param FetchRequestTemplate|null $selectedFetchRequestTemplate
     * @param Configuration|null $selectedConfiguration
     * @param ArrayClass<ManagedObject> $breadcrumb
     */
    public function __construct(public string $title, public ArrayClass $projects, public ?Project $project, public ArrayClass $allEntities, public ArrayClass $rootEntities, public ArrayClass $fetchRequestTemplates, public ArrayClass $configurations, public ?Entity $selectedEntity, public ?Property $selectedProperty, public ?FetchIndex $selectedIndex, public ?FetchIndexElement $selectedIndexElement, public ?UniquenessConstraint $selectedUniquenessConstraint, public ?FetchRequestTemplate $selectedFetchRequestTemplate, public ?Configuration $selectedConfiguration, public ArrayClass $breadcrumb)
    {
    }
}