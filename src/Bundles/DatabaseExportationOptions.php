<?php

declare(strict_types=1);

namespace App\Bundles;

final readonly class DatabaseExportationOptions
{
    /**
     * @param bool $withData Whether to include row data in the exported dump, not just schema.
     * @param bool $withComments Whether to include descriptive comments in the exported dump.
     */
    public function __construct(public bool $withData = false, public bool $withComments = false)
    {
    }
}
