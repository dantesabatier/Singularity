<?php

declare(strict_types=1);

namespace App\Responders;

use Override;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\MethodNotAllowedException;
use Sabatier\Service\Responder;
use Sabatier\Service\Response;
use Sabatier\Service\ResponsePipeline;

#[Endpoint("Info", transformers: [HTMLTransformer::class])]
final class InfoResponder extends Responder
{
    #[Override]
    public bool $isProtectedContentAvailable = true;
    #[Override]
    public Response $response {
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                phpinfo();
                return new ResponsePipeline($this->transformers->union($this->infrastructureTransformers), $this->transformerContext)->process(new Response($this->request->url));
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
