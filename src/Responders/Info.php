<?php

namespace App\Responders;

use Sabatier\Service\Endpoint;
use Sabatier\Service\Responder;
use Sabatier\Service\Response;

#[Endpoint]
class Info extends Responder
{
    public bool $isProtectedContentAvailable = true;
    public Response $response {
        get {
            phpinfo();
            return new Response($this->response->url);
        }
    }
}
