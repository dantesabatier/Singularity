<?php

declare(strict_types=1);

namespace App\Model;

use Sabatier\CoreData\EntityMappingType;

/**
 * How an entity is mapped from the source version to the destination one.
 *
 * The cases mirror {@see EntityMappingType}, which is what the engine reads once the map is archived.
 */
enum EntityMapType: int
{
    /** The developer handles destination instance creation. */
    case undefined = 0;
    /** A custom mapping, which needs a migration policy to carry it out. */
    case custom = 1;
    /** The entity is new in the destination version. */
    case add = 2;
    /** The entity is gone from the destination version. */
    case remove = 3;
    /** Source instances migrate as they are. */
    case copy = 4;
    /** The entity exists on both sides and its properties are mapped. */
    case transform = 5;

    /**
     * Returns the mapping type the engine reads for this case.
     */
    public function entityMappingType(): EntityMappingType
    {
        return EntityMappingType::from($this->value);
    }
}
