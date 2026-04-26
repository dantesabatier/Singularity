<?php

declare(strict_types=1);

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;

final readonly class OpenBundleTransaction implements Transaction
{
    public Project $project;

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
