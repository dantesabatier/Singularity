<?php

namespace App\ViewControllers;

use App\AI\LLMProvider;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;
use const App\CompanyNamePreferencesKey;
use const App\EditorAIModelPreferencesKey;
use const App\EditorAIProviderPreferencesKey;
use const App\EditorAIProvidersPreferencesKey;
use const App\EditorCopilotEnabledPreferencesKey;
use const App\EditorSelectedViewPreferencesKey;
use const App\EditorSplitSizesPreferencesKey;
use const App\ExportIncludeCommentsPreferencesKey;
use const App\ExportIncludeDataPreferencesKey;
use const App\ExportLastDirectoryPreferencesKey;

#[Endpoint("Preferences", transformers: [HTMLTransformer::class])]
final class PreferencesController extends ViewController
{
    #[Override]
    protected string $name = "Preferences";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
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
    public ?string $editorSelectedView {
        get => UserDefaults::standard()->string(EditorSelectedViewPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorSelectedViewPreferencesKey);
        }
    }
    #[Outlet]
    public ?ArrayClass $editorSplitSizes {
        get => UserDefaults::standard()->array(EditorSplitSizesPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorSplitSizesPreferencesKey);
        }
    }
    #[Outlet]
    public bool $editorCopilotEnabled {
        get => UserDefaults::standard()->bool(EditorCopilotEnabledPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, EditorCopilotEnabledPreferencesKey);
        }
    }
    #[Outlet]
    public ?string $exportLastDirectory {
        get => UserDefaults::standard()->string(ExportLastDirectoryPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, ExportLastDirectoryPreferencesKey);
        }
    }
    #[Outlet]
    public bool $exportIncludeData {
        get => UserDefaults::standard()->bool(ExportIncludeDataPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, ExportIncludeDataPreferencesKey);
        }
    }
    #[Outlet]
    public bool $exportIncludeComments {
        get => UserDefaults::standard()->bool(ExportIncludeCommentsPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, ExportIncludeCommentsPreferencesKey);
        }
    }
    #[Outlet]
    public ?string $editorAIProvider {
        get => UserDefaults::standard()->string(EditorAIProviderPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorAIProviderPreferencesKey);
        }
    }
    #[Outlet]
    public ?string $editorAIModel {
        get => UserDefaults::standard()->string(EditorAIModelPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorAIModelPreferencesKey);
        }
    }
    #[Outlet]
    public ?ArrayClass $editorAIProviders {
        get => UserDefaults::standard()->array(EditorAIProvidersPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorAIProvidersPreferencesKey);
        }
    }
    /** @var ArrayClass<LLMProvider> */
    #[Outlet]
    private(set) ArrayClass $aiProviders {
        get => $this->aiProviders ??= LLMProvider::all();
    }

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "Preferences";
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function synchronize(): void
    {
        $parameters = $this->request->parameters;
        foreach ($parameters as $key => $value) {
            $this->$key = $value;
        }
        $this->data = UserDefaults::standard()->dictionaryRepresentation();
    }
}
