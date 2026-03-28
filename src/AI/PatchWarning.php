<?php

namespace App\AI;

use Override;
use Sabatier\Foundation\ObjectClass;
use const App\UndefinedStringValue;

final class PatchWarning extends ObjectClass
{
    public string $message = UndefinedStringValue {
        set {
            $this->message = $value |> trim(...);
        }
    }
    public ?string $code = null {
        set {
            $this->code = $value;
            if ($this->code !== null) {
                $this->code = $this->code |> trim(...);
            }
        }
    }
    public ?string $path = null {
        set {
            $this->path = $value;
            if ($this->path !== null) {
                $this->path = $this->path |> trim(...);
            }
        }
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["message" => $this->message, "code" => $this->code, "path" => $this->path];
    }
}
