<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;

/**
 * @property string $name
 * @property string $identifier
 * @property Date $executionDate
 * @property ToolCallStatus $status
 * @property Dictionary<mixed> $arguments
 * @property string|null $result
 * @property Message|null $message
 */
final class ToolCall extends ManagedObject
{
    public function validateStatus(ToolCallStatus|int|null &$status): bool
    {
        if (is_int($status)) {
            $status = ToolCallStatus::from($status);
        }
        return true;
    }
}
