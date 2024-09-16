<?php

namespace App\Contexts;

use App\Model\Project;
use Sabatier\Foundation\ArrayClass;

class Welcome
{
    /**
     * @param string $title
     * @param ArrayClass<Project> $projects
     * @param array{applicationName: string, applicationVersion: string, copyright: string, version: string} $info
     */
    public function __construct(public string $title, public ArrayClass $projects, public array $info)
    {
    }
}
