<?php

namespace App\Bundles;

use Exception;

interface CompensableTransaction extends Transaction
{
    /**
     * @throws Exception
     */
    public function rollback(): void;
}
