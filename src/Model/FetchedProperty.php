<?php

namespace App\Model;

use Sabatier\Foundation\Dictionary;

/**
 * @property string $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 */
class FetchedProperty extends Property
{
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
        $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
        return $dictionary;
    }
}
