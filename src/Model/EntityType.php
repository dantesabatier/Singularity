<?php

declare(strict_types=1);

namespace App\Model;

enum EntityType: int
{
    case none = 0;
    case authorizable = 1;
    case authorizableRole = 2;
    case authorization = 3;
}
