<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use const Sabatier\CoreData\ManagedObjectValidationError;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * @property string|null $expressionFormat
 * @property AttributeType $resultType
 */
class ExpressionDescriptionTemplate extends Property
{
    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $observation = $this->observe("resultType", KeyValueObservingOptions::new, function (ExpressionDescriptionTemplate $expression, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            $change->newValue !== AttributeType::undefined ?: throw new InternalInconsistencyException(error: new Error(CocoaErrorDomain, ManagedObjectValidationError, new Dictionary([LocalizedDescriptionKey => "{$expression->entityProperty->name}.$expression->name must be a defined type", LocalizedFailureReasonErrorKey => "{$expression->entityProperty->name}.$expression->name cannot use an attribute type of \"Undefined\""])));
        });
    }

    public function validateType(AttributeType|int|null &$type): bool
    {
        if (is_int($type)) {
            $type = AttributeType::from($type);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $resultType = $this->resultType;
        if ($resultType !== AttributeType::undefined) {
            $dictionary["resultType"] = $resultType;
        }
        $dictionary["expressionFormat"] = $this->expressionFormat;
        return $dictionary;
    }
}
