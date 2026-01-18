<?php

namespace App\ViewControllers;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;

#[Endpoint("Preferences")]
final class PreferencesController extends ViewController
{
    public string $name = "Preferences";
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
    #[Outlet]
    public ?string $companyName {
        get => UserDefaults::standard()->string(__PROPERTY__);
        set {
            UserDefaults::standard()->setObject($value, __PROPERTY__);
        }
    }
    #[Outlet]
    public bool $automaticallyDeleteProjectFolders {
        get => UserDefaults::standard()->bool(__PROPERTY__);
        set {
            UserDefaults::standard()->setBool($value, __PROPERTY__);
        }
    }
    public ?string $editorSelectedView {
        get => UserDefaults::standard()->string(__PROPERTY__);
        set {
            UserDefaults::standard()->setObject($value, __PROPERTY__);
        }
    }
    public float $graphViewZoom {
        get => UserDefaults::standard()->float(__PROPERTY__);
        set {
            UserDefaults::standard()->setFloat($value, __PROPERTY__);
        }
    }
    public Dictionary $graphViewPan {
        get => UserDefaults::standard()->dictionary(__PROPERTY__);
        set {
            UserDefaults::standard()->setObject($value, __PROPERTY__);
        }
    }

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "Preferences";
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
