<?php

namespace App\ViewControllers;

use App\Model\Project;
use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;

abstract class ProjectViewController extends ViewController
{
    #[Outlet]
    public Project $project {
        get => $this->project ??= $this->loadProject();
    }

    protected function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->httpMethod === HTTPRequestMethod::get ? new URLComponents($this->request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $this->request->parsedBody[$key] ?? null;
        return is_numeric($referenceObject) ? (int)$referenceObject : null;
    }

    /**
     * @throws Exception
     */
    private function loadProject(): Project
    {
        if (!($referenceObject = $this->referenceObject("project"))) {
            throw new NotFoundException();
        }
        return $this->fetchProjectByReference($referenceObject) ?? throw new NotFoundException();
    }

    /**
     * @throws Exception
     */
    private function fetchProjectByReference(int $referenceObject): ?Project
    {
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
        $fetchRequest->serialization = Dictionary::dictionaryWithArray([
            "name" => AttributeType::string,
            "url" => AttributeType::uri,
            "color" => AttributeType::string
        ]);
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? null;
    }
}
