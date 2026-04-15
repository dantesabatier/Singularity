<?php

namespace App\Responders;

use Override;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\Responder;
use Sabatier\Service\Response;

#[Endpoint("Info", transformers: [HTMLTransformer::class])]
final class InfoResponder extends Responder
{
    #[Override]
    public bool $isProtectedContentAvailable = true;
    #[Override]
    public Response $response {
        get {
            phpinfo();
            return new Response($this->request->url);
        }
    }
}
