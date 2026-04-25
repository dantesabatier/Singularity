<?php

namespace App\Cache;

use Sabatier\Service\CacheHeaderTransformer;
use Sabatier\Service\HTTPCachePolicy;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class PrivateCacheHeaderTransformer extends CacheHeaderTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        parent::__construct($response, new ResponseTransformerContext(request: $context->request, cachePolicy: new HTTPCachePolicy(maxAge: 0, visibility: "private"), corsPolicy: $context->corsPolicy, securityHeadersPolicy: $context->securityHeadersPolicy, rateLimitInfo: $context->rateLimitInfo));
    }
}
