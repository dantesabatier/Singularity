<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;
use const App\CompanyNamePreferencesKey;

#[Endpoint("Preferences")]
final class PreferencesController extends ViewController
{
    public string $name = "Preferences";
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

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function synchronize(): void
    {
        $body = $this->request->parsedBody;
        foreach ($body as $key => $value) {
            $this->$key = $value;
        }
        $this->data = UserDefaults::standard()->dictionaryRepresentation();
    }
}
