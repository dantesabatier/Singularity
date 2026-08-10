<?php

declare(strict_types=1);

namespace App\Bundles;

use Exception;
use Override;

final readonly class SaveBundleTransaction implements Transaction
{
    /**
     * @param BundleUpdater $updater The updater used to bring the bundle's generated files up to date.
     */
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
