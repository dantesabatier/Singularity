<?php

namespace App\Model;

use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 */
class FetchedProperty extends Property
{
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = parent::$dictionaryRepresentation->get();
            $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
            $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
            return $dictionary;
        }
    }
}
