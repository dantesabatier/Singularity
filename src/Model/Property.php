<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string $name
 * @property bool $isOptional
 * @property bool $isTransient
 * @property string|null $renamingIdentifier
 * @property string|null $versionHashModifier
 * @property mixed $minValue
 * @property mixed $maxValue
 * @property string|null $regex
 * @property Entity $entityProperty
 * @property bool $usesMinValue
 * @property bool $usesMaxValue
 */
abstract class Property extends ManagedObject
{
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        if (!($isOptional = $this->isOptional)) {
            $dictionary["isOptional"] = $isOptional;
        }
        if ($isTransient = $this->isTransient) {
            $dictionary["isTransient"] = $isTransient;
        }
        $dictionary["versionHashModifier"] = $this->versionHashModifier;
        $dictionary["renamingIdentifier"] = $this->renamingIdentifier;
        $dictionary["minValue"] = $this->minValue;
        $dictionary["maxValue"] = $this->maxValue;
        if ($regex = $this->regex) {
            $dictionary["regex"] = $regex;
        }
        return $dictionary;
    }
}
