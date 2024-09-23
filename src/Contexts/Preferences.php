<?php

namespace App\Contexts;

readonly class Preferences
{
    public function __construct(public string $title, public ?string $companyName, public bool $automaticallyDeleteProjectFolders, public bool $automaticallySaveModel)
    {
    }
}