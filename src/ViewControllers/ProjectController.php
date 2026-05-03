<?php

namespace App\ViewControllers;

use App\Model\Project;
use Exception;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;

abstract class ProjectController extends FetchController
{
    #[Outlet]
    public Project $project {
        /**
         * @throws Exception
         */
        get => $this->project ??= $this->loadProject();
    }

    /**
     * @throws Exception
     */
    protected function loadProject(): Project
    {
        if (!($referenceObject = $this->referenceObject("project"))) {
            throw new BadRequestException("Project reference is required");
        }
        return $this->fetchByReference(Project::class, $referenceObject) ?? throw new NotFoundException("Project not found");
    }
}
