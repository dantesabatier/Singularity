<?php

declare(strict_types=1);

namespace App;

final readonly class ViteAsset
{
    /**
     * @param string|null $client The Vite dev server client script URL, or `null` when serving the production build.
     * @param list<string> $css Stylesheet URLs to include for this entry.
     * @param list<string> $js Script URLs to include for this entry.
     */
    public function __construct(public ?string $client = null, public array $css = [], public array $js = [])
    {
    }
}
