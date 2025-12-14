<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use const App\EntityPositionsMappingPreferencesKey;

#[Endpoint("Viewer")]
final class ViewerController extends ProjectController
{
    public string $name = "Viewer";
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
