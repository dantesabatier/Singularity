<?php

declare(strict_types=1);

namespace App\Bundles;

use Exception;
use Override;

final readonly class SaveBundleTransaction implements Transaction
{
    public function __construct(private BundleUpdater $updater)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $this->updater->update();
    }
}
