<?php

declare(strict_types=1);

namespace App\ViewControllers;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\JSONTransformer;
use App\LLM\Provider;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;
use const App\AutomaticallyDeleteMappingModelFilesPreferencesKey;
use const App\CompanyNamePreferencesKey;
use const App\EditorCopilotEnabledPreferencesKey;
use const App\EditorSelectedViewPreferencesKey;
use const App\EditorSplitSizesPreferencesKey;
use const App\ExportIncludeCommentsPreferencesKey;
use const App\ExportIncludeDataPreferencesKey;
use const App\ExportLastDirectoryPreferencesKey;
use const App\LLMModelPreferencesKey;
use const App\LLMProviderPreferencesKey;
use const App\LLMProvidersPreferencesKey;

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
    public bool $automaticallyDeleteMappingModelFiles {
        get => UserDefaults::standard()->bool(AutomaticallyDeleteMappingModelFilesPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, AutomaticallyDeleteMappingModelFilesPreferencesKey);
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
        get => UserDefaults::standard()->string(LLMProviderPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, LLMProviderPreferencesKey);
        }
    }
    #[Outlet]
    public ?string $editorAIModel {
        get => UserDefaults::standard()->string(LLMModelPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, LLMModelPreferencesKey);
        }
    }
    #[Outlet]
    public ?ArrayClass $editorAIProviders {
        get => UserDefaults::standard()->array(LLMProvidersPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, LLMProvidersPreferencesKey);
        }
    }
    /** @var ArrayClass<Provider> */
    #[Outlet]
    private(set) ArrayClass $aiProviders {
        get => $this->aiProviders ??= Provider::all();
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
            if ($key === "editorAIProviders") {
                $value = $this->editorAIProvidersWithPreservedAPIKeys($value);
            }
            $this->$key = $value;
        }
        $this->data = UserDefaults::standard()->dictionaryRepresentation();
    }

    /** @param ArrayClass<Dictionary<mixed>> $providers */
    private function editorAIProvidersWithPreservedAPIKeys(ArrayClass $providers): ArrayClass
    {
        /** @var ArrayClass<Dictionary<mixed>> $storedProviders */
        $storedProviders = UserDefaults::standard()->array(LLMProvidersPreferencesKey) ?? new ArrayClass();
        /** @var array<string, Dictionary<mixed>> $storedProvidersByIdentifier */
        $storedProvidersByIdentifier = [];
        foreach ($storedProviders as $storedProvider) {
            $storedProvidersByIdentifier[(string)$storedProvider["identifier"]] = $storedProvider;
        }
        return $providers->map(function (Dictionary $provider) use ($storedProvidersByIdentifier): Dictionary {
            $identifier = (string)$provider["identifier"];
            if ($identifier === "") {
                return $provider;
            }
            $apiKey = (string)($provider["apiKey"] ?? "");
            if ($apiKey !== "") {
                return $provider;
            }
            $storedProvider = $storedProvidersByIdentifier[$identifier] ?? null;
            if (!$storedProvider) {
                return $provider;
            }
            $provider["apiKey"] = $storedProvider["apiKey"] ?? "";
            return $provider;
        });
    }
}
