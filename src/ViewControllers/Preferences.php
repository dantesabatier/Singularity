<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\CompanyNameKey;

#[Endpoint("/Preferences")]
class Preferences extends ViewController
{
    #[Outlet]
    public ?string $companyName = null;

    public function viewWillLoad(): void
    {
        $this->companyName = UserDefaults::standard()->string(CompanyNameKey);
    }

    /**
     * @throws Exception
     */
    #[Action("/Synchronize")]
    public function synchronize(): void
    {
        $body = $this->request->getParsedBody();
        foreach ($body as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }
}
