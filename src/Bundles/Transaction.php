<?php

declare(strict_types=1);

namespace App\Bundles;

use Exception;

interface Transaction
{
    /**
     * @throws Exception
     */
    public function execute(): void;
}
