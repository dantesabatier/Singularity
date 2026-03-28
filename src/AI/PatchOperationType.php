<?php

namespace App\AI;

enum PatchOperationType: string
{
    case undefined = "undefined";
    case createEntity = "createEntity";
    case addAttribute = "addAttribute";
    case addRelationship = "addRelationship";
}
