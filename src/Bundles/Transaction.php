<?php

namespace App\Bundles;

use Exception;

interface Transaction
{
    /**
     * @throws Exception
     */
    public function execute(): void;
}
