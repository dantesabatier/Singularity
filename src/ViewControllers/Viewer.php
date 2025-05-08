<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\Model\Project;
use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const App\SQLByEntityPositionsMappingTablePreferencesKey;

#[Endpoint]
class Viewer extends ViewController
{
    #[Outlet]
    public Project $project {
        get {
            if (!isset($this->project)) {
                if (!($referenceObject = $this->referenceObject("project"))) {
                    throw new NotFoundException();
                }
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
                $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                    "name" => AttributeType::string,
                    "url" => AttributeType::uri,
                    "color" => AttributeType::string
                ]);
                $this->project = $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException();
            }
            return $this->project;
        }
    }
    #[Outlet]
    public Dictionary $schema {
        get => $this->project->model?->schema;
    }

    private function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->httpMethod === HTTPRequestMethod::get ? new URLComponents($this->request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $this->request->parsedBody[$key] ?? null;
        if (is_numeric($referenceObject)) {
            return (int)$referenceObject;
        }
        return null;
    }

    /**
     * @throws Exception
     */
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
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
