<?php

declare(strict_types=1);

namespace App\AI;

enum PatchOperationType: string
{
    case undefined = "undefined";
    case createEntity = "createEntity";
    case addAttribute = "addAttribute";
    case addRelationship = "addRelationship";
}
