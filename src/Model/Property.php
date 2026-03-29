<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property bool $isOptional
 * @property bool $isTransient
 * @property bool $isSensitive
 * @property string|null $renamingIdentifier
 * @property string|null $versionHashModifier
 * @property mixed $minValue
 * @property mixed $maxValue
 * @property bool $isMinValueBounded
 * @property bool $isMaxValueBounded
 * @property string|null $regex
 * @property int $position
 * @property Entity|null $entityProperty
 * @property Set<AccessControl> $accessControls
 * @method void addAccessControlsObject(AccessControl $object)
 * @method void removeAccessControlsObject(AccessControl $object)
 * @method void addAccessControls(Set<AccessControl> $objects)
 * @method void removeAccessControls(Set<AccessControl> $objects)
 * @method Set<AccessControl> intersectAccessControls(Set<AccessControl> $objects)
 * @method void setAccessControls(Set<AccessControl> $objects)
 */
abstract class Property extends ManagedObject
{
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            if (!($isOptional = $this->isOptional)) {
                $dictionary["isOptional"] = $isOptional;
            }
            if ($isTransient = $this->isTransient) {
                $dictionary["isTransient"] = $isTransient;
            }
            if ($isSensitive = $this->isSensitive) {
                $dictionary["isSensitive"] = $isSensitive;
            }
            $dictionary["versionHashModifier"] = $this->versionHashModifier;
            $dictionary["renamingIdentifier"] = $this->renamingIdentifier;
            if ($regex = $this->regex) {
                $dictionary["regex"] = $regex;
            }
            $isMinValueBounded = $this->isMinValueBounded;
            if ($isMinValueBounded) {
                $dictionary["isMinValueBounded"] = $isMinValueBounded;
            }
            $isMaxValueBounded = $this->isMaxValueBounded;
            if ($isMaxValueBounded) {
                $dictionary["isMaxValueBounded"] = $isMaxValueBounded;
            }
            $dictionary["minValue"] = $isMinValueBounded ? $this->minValue : null;
            $dictionary["maxValue"] = $isMaxValueBounded ? $this->maxValue : null;
            $accessControls = $this->accessControls;
            if (!$accessControls->isEmpty) {
                $dictionary["accessControls"] = $accessControls->map(fn(AccessControl $accessControl): Dictionary => $accessControl->dictionaryRepresentation);
            }
            return $dictionary;
        }
    }

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
