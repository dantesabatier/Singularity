<?php

namespace App\ViewControllers;

use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFolders;
use const App\AutomaticallySaveModel;
use const App\CompanyNameKey;

#[Endpoint]
class Preferences extends ViewController
{
    #[Outlet]
    public ?string $companyName = null;
    #[Outlet]
    public bool $automaticallyDeleteProjectFolders = false;
    #[Outlet]
    public bool $automaticallySaveModel = false;

    public function viewWillLoad(): void
    {
        $this->companyName = UserDefaults::standard()->string(CompanyNameKey);
        $this->automaticallyDeleteProjectFolders = UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders);
        $this->automaticallySaveModel = UserDefaults::standard()->bool(AutomaticallySaveModel);
    }

    #[Action]
    public function synchronize(): void
    {
        $body = $this->request->getParsedBody();
        foreach ($body as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }
}
