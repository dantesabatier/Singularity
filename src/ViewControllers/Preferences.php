<?php

namespace App\ViewControllers;

use Exception;
use Override;
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

    #[Override]
    public function viewWillLoad(): void
    {
        $this->companyName = UserDefaults::standard()->string(CompanyNameKey);
        $this->automaticallyDeleteProjectFolders = UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders);
        $this->automaticallySaveModel = UserDefaults::standard()->bool(AutomaticallySaveModel);
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
        $this->contentType = "application/json";
    }
}
