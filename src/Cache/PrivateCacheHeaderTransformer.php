<?php

namespace App\Cache;

use Sabatier\Service\CacheHeaderTransformer;
use Sabatier\Service\HTTPCachePolicy;
use Sabatier\Service\Response;

final class PrivateCacheHeaderTransformer extends CacheHeaderTransformer
{
    public function __construct(Response $response)
    {
        parent::__construct($response, new HTTPCachePolicy(maxAge: 60, visibility: 'private'));
    }
}
