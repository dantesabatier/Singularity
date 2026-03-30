<?php

namespace App\AI;

use JsonSerializable;
use Override;

final readonly class PatchWarning implements JsonSerializable
{
    public function __construct(public string $message, public ?string $code = null, public ?string $path = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["message" => $this->message, "code" => $this->code, "path" => $this->path];
    }
}
