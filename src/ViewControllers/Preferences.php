<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFolders;
use const App\CompanyNameKey;

#[Endpoint]
class Preferences extends ViewController
{
    #[Outlet]
    public ?string $companyName = null;
    #[Outlet]
    public bool $automaticallyDeleteProjectFolders = false;

    public function viewWillLoad(): void
    {
        $this->companyName = UserDefaults::standard()->string(CompanyNameKey);
        $this->automaticallyDeleteProjectFolders = UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function synchronize(): void
    {
        $body = $this->request->getParsedBody();
        foreach ($body as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }

    public function response(): HTTPURLResponse
    {
        return new HTTPURLResponse($this->request->url, HTTPStatusCode::noContent);
    }
}
