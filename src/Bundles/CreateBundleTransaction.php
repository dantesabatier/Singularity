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
