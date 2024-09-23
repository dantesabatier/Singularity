<?php

namespace App\Contexts;

use App\Model\Project;
use Sabatier\Foundation\ArrayClass;

readonly class Welcome
{
    /**
     * @param string $title
     * @param ArrayClass<Project> $projects
     * @param array{applicationName: string, applicationVersion: string, copyright: string, version: string} $about
     */
    public function __construct(public string $title, public ArrayClass $projects, public array $about)
    {
    }
}
