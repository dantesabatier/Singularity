<?php

namespace App\Model;

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
 * @property int<0, max> $position
 * @property Entity|null $entityProperty
 * @property Set<Annotation> $annotations
 * @method void addAnnotationsObject(Annotation $object)
 * @method void removeAnnotationsObject(Annotation $object)
 * @method void addAnnotations(Set $objects)
 * @method void removeAnnotations(Set $objects)
 * @method Set<Annotation> intersectAnnotations(Set $objects)
 * @method void setAnnotations(Set $objects)
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
            return $dictionary;
        }
    }
}
