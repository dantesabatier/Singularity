<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

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
use const App\EntityPositionsMappingPreferencesKey;

#[Endpoint("Viewer")]
final class ViewerController extends ProjectController
{
    public string $name = "Viewer";
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
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
        $transaction = new ExportDatabaseTransaction($this->project, $destinationDirectory);
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
