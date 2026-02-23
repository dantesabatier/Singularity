<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\Bundles\DatabaseExportationOptions;
use App\Bundles\ExportDatabaseTransaction;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\Outlet;
use const App\EntityPositionsMappingPreferencesKey;
use const App\ExportIncludeCommentsPreferencesKey;
use const App\ExportIncludeDataPreferencesKey;
use const App\ExportLastDirectoryPreferencesKey;

#[Endpoint("Viewer")]
final class ViewerController extends ProjectController
{
    #[Override]
    public string $name = "Viewer";
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
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

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "SQL Schema";
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function export(): void
    {
        $body = $this->request->parsedBody;
        $directory = $body["directory"] ?? throw new BadRequestException();
        $destinationDirectory = URL::fileURL($directory);
        $options = new DatabaseExportationOptions($this->exportIncludeData, $this->exportIncludeComments);
        $transaction = new ExportDatabaseTransaction($this->project, $destinationDirectory, $options);
        $transaction->execute();
        $this->data = ["url" => $transaction->fileURL];
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function moved(): void
    {
        $name = $this->project->name;
        $body = $this->request->parsedBody;
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = UserDefaults::standard()->dictionary(EntityPositionsMappingPreferencesKey) ?? new Dictionary();
        /** @var Dictionary<mixed> $dictionary */
        $project = $dictionary[$name] ?? new Dictionary();
        $project[$body["name"]] = $body["pos"];
        $dictionary[$name] = $project;
        UserDefaults::standard()->setObject($dictionary, EntityPositionsMappingPreferencesKey);
        $this->data = [];
    }
}
