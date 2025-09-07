<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;
use const App\AutomaticallySaveModelPreferencesKey;
use const App\CompanyNamePreferencesKey;

#[Endpoint]
class Preferences extends ViewController
{
    #[Outlet]
    public ?string $companyName {
        get => UserDefaults::standard()->string(CompanyNamePreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, CompanyNamePreferencesKey);
        }
    }
    #[Outlet]
    public bool $automaticallyDeleteProjectFolders {
        get => UserDefaults::standard()->bool(AutomaticallyDeleteProjectFoldersPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, AutomaticallyDeleteProjectFoldersPreferencesKey);
        }
    }
    #[Outlet]
    public bool $automaticallySaveModel {
        get => UserDefaults::standard()->bool(AutomaticallySaveModelPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, AutomaticallySaveModelPreferencesKey);
        }
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function synchronize(): void
    {
        $body = $this->request->parsedBody;
        foreach ($body as $key => $value) {
            $this->$key = $value;
        }
        $this->content = json_encode(UserDefaults::standard()->dictionaryRepresentation(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
