<?php

declare(strict_types=1);

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;

final readonly class OpenBundleTransaction implements Transaction
{
    public Project $project;

    /**
     * @param ProjectBundleLoader $loader The loader used to open the project from its bundle.
     */
    public function __construct(private ProjectBundleLoader $loader)
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
}
