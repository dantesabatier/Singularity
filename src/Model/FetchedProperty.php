<?php

namespace App\Model;

use Override;
use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 */
class FetchedProperty extends Property
{
    #[Override]
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
        $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
        return $dictionary;
    }
}
