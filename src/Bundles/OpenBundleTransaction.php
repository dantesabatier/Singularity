<?php

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;
use function Sabatier\Foundation\fatal_error;

final class OpenBundleTransaction implements Transaction
{
    private ?Project $project = null;

    public function __construct(private readonly ProjectBundleLoader $loader)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $this->project = $this->loader->load();
    }

    public function project(): Project
    {
        return $this->project ?? fatal_error("Transaction not executed");
    }
}
