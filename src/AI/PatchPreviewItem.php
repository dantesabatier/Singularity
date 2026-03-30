<?php

namespace App\AI;

use JsonSerializable;
use Override;
use Sabatier\Foundation\Dictionary;

final readonly class PatchPreviewItem implements JsonSerializable
{
    public function __construct(public string $title, public string $content, public string $level = "info", public string $code = "", public ?Dictionary $meta = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["title" => $this->title, "content" => $this->content, "level" => $this->level, "code" => $this->code, "meta" => $this->meta];
    }
}
