<?php

namespace App\ViewControllers;

use Exception;
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
    public ?string $companyName {
        get => UserDefaults::standard()->string(CompanyNameKey);
    }
    #[Outlet]
    public bool $automaticallyDeleteProjectFolders {
        get => UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders);
    }
    #[Outlet]
    public bool $automaticallySaveModel {
        get => UserDefaults::standard()->bool(AutomaticallySaveModel);
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
        $this->content = json_encode(UserDefaults::standard()->dictionaryRepresentation(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
