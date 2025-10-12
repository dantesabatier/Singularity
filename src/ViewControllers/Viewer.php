<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use const App\EntityPositionsMappingPreferencesKey;

#[Endpoint]
final class Viewer extends ProjectViewController
{
    /**
     * @throws Exception
     */
    #[Action]
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
        $this->content = json_encode([], JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
