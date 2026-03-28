<?php

namespace App\Responders;

use Override;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLDecorator;
use Sabatier\Service\Responder;
use Sabatier\Service\Response;

#[Endpoint("Info", decorators: [HTMLDecorator::class])]
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
