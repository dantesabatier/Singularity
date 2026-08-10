<?php

declare(strict_types=1);

namespace App\Bundles;

use Exception;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class CreateBundleTransaction implements CompensableTransaction
{
    private bool $executed = false;

    /**
     * @param BundleScaffolder $scaffolder The scaffolder used to generate the bundle's files.
     * @param URL $bundleURL The destination directory the bundle is created in, used to roll back on failure.
     */
    public function __construct(private readonly BundleScaffolder $scaffolder, private readonly URL $bundleURL)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        try {
            $this->scaffolder->scaffold();
            $this->executed = true;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function rollback(): void
    {
        if (!$this->executed) {
            FileManager::default()->removeItem($this->bundleURL);
        }
    }
}
