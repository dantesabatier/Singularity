<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;

/**
 * @property Date $creationDate
 * @property string $role
 * @property string|null $content
 * @property string|null $toolCalls
 * @property string|null $toolCallId
 * @property int<0, max> $position
 * @property Conversation|null $conversation
 */
final class Message extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->role = $this->role |> trim(...);
        if ($this->content) {
            $this->content = $this->content |> trim(...);
        }
        if ($this->toolCallId) {
            $this->toolCallId = $this->toolCallId |> trim(...);
        }
    }
}
