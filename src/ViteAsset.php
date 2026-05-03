<?php

declare(strict_types=1);

namespace App;

final readonly class ViteAsset
{
    /**
     * @param string|null $client
     * @param list<string> $css
     * @param list<string> $js
     */
    public function __construct(public ?string $client = null, public array $css = [], public array $js = [])
    {
    }
}
