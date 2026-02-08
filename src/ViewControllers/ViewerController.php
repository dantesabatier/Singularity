<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use Exception;
use Override;
use Sabatier\CoreData\SQLSchema;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use function Sabatier\Foundation\fatal_error;
use const App\EntityPositionsMappingPreferencesKey;
use const Sabatier\Foundation\kCFBundleNameKey;

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
    public function exportSQL(): void
    {
        $body = $this->request->parsedBody;
        $directory = $body["directory"] ?? throw new BadRequestException();
        $project = $this->project;
        /** @var URL $url */
        $url = $project->url;
        $bundle = Bundle::bundleWithURL($url);
        /** @var string $name */
        $name = $bundle->object(kCFBundleNameKey);
        $schema = SQLSchema::schema($name);
        $credential = $schema->credential;
        $date = new Date()->format("Y-m-d_H-i-s");
        $fileURL = URL::fileURL($directory)->appendingPathComponent("backup_{$schema->name}_$date.sql");
        $command = "mariadb-dump --user=$credential->user --password=$credential->password --host=$schema->host $schema->name > $fileURL->path";
        system($command, $result);
        $result !== 0 ?: fatal_error("Unable to export the database");
        $this->data = ["url" => $fileURL];
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
