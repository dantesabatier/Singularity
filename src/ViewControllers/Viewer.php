<?php /** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\Model\Project;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Service\Endpoint;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;

#[Endpoint]
class Viewer extends ViewController
{
    #[Outlet]
    public Dictionary $schema {
        get {
            if (!($referenceObject = new URLComponents($this->request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === "project")?->value)) {
                throw new NotFoundException();
            }
            $fetchRequest = Project::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
            $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                "name" => AttributeType::string,
                "url" => AttributeType::uri,
                "color" => AttributeType::string,
                "model" => [
                    "url" => AttributeType::uri
                ]
            ]);
            /** @var Project $project */
            $project = $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException();
            return $project->model?->schema;
        }
    }
}
