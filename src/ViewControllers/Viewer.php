<?php

namespace App\ViewControllers;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use const App\SQLByEntityPositionsMappingTablePreferencesKey;

#[Endpoint]
class Viewer extends ProjectViewController
{
    #[Outlet]
    public Dictionary $schema {
        get => $this->project->model?->schema;
    }

    #[Action]
    public function moved(): void
    {
        $name = $this->project->name;
        $body = $this->request->parsedBody;
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = UserDefaults::standard()->dictionary(SQLByEntityPositionsMappingTablePreferencesKey) ?? new Dictionary();
        /** @var Dictionary<mixed> $dictionary */
        $project = $dictionary[$name] ?? new Dictionary();
        $project[$body["name"]] = $body["pos"];
        $dictionary[$name] = $project;
        UserDefaults::standard()->setObject($dictionary, SQLByEntityPositionsMappingTablePreferencesKey);
        $this->content = json_encode([]);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
