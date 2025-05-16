<?php

namespace App\ViewControllers;

use App\Model\Project;
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

    protected function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->httpMethod === HTTPRequestMethod::get ? new URLComponents($this->request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $this->request->parsedBody[$key] ?? null;
        return is_numeric($referenceObject) ? (int)$referenceObject : null;
    }
}
