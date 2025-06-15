<?php

namespace App\Model;

use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 * @property string|null $fetchRequestSortDescriptorKey
 * @property bool $fetchRequestSortDescriptorIsAscending
 */
class FetchedProperty extends Property
{
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = parent::$dictionaryRepresentation::get();
            $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
            $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
            return $dictionary;
        }
    }
}
