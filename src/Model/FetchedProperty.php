<?php

namespace App\Model;

use Override;
use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 * @property string|null $fetchRequestSortDescriptorKey
 * @property bool $fetchRequestSortDescriptorIsAscending
 */
final class FetchedProperty extends Property
{
    /** @var Dictionary<mixed> */
    #[Override]
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = parent::$dictionaryRepresentation::get();
            $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
            $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
            $dictionary["fetchRequestSortDescriptorKey"] = $this->fetchRequestSortDescriptorKey;
            $fetchRequestSortDescriptorIsAscending = $this->fetchRequestSortDescriptorIsAscending;
            if (!$fetchRequestSortDescriptorIsAscending) {
                $dictionary["fetchRequestSortDescriptorIsAscending"] = $fetchRequestSortDescriptorIsAscending;
            }
            return $dictionary;
        }
    }
}
