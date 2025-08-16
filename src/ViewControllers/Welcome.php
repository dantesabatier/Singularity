<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\random_color;
use const Sabatier\Foundation\kCFBundleNameKey;

#[Endpoint("/")]
class Welcome extends ViewController
{
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        get {
            if (!isset($this->projects)) {
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->propertiesToFetch = new ArrayClass(["name", "creationDate", "url", "color"]);
                $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
                $this->projects = $this->managedObjectContext->fetch($fetchRequest);
            }
            return $this->projects;
        }
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function create(): void
    {
        $body = $this->request->parsedBody;
        $path = $body["path"] ?? throw new BadRequestException();
        $url = URL::fileURL($path);
        $name = $url->lastPathComponent;
        $model = new Model($this->managedObjectContext);
        $model->url = $url->appendingPathComponent("Resources")->appendingPathComponent($name)->appendingPathExtension("plist");
        $project = new Project($this->managedObjectContext);
        $project->creationDate = new Date();
        $project->name = $name;
        $project->url = $url;
        $project->color = random_color();
        $project->model = $model;
        $fileWriter = new ProjectFileWriter($url, $project);
        $fileWriter->save();
        $this->content = json_encode($project, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::patch)]
    public function rename(): void
    {
        $body = $this->request->parsedBody;
        $name = $body["name"] ?? throw new BadRequestException("name cannot be null");
        $objectID = $body[SQLEntity::primaryKeyName] ?? throw new BadRequestException();
        $context = $this->managedObjectContext;
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
        $project = $context->fetch($fetchRequest)->first ?? throw new NotFoundException();
        $project->name = $name;
        $context->save();
        /** @var URL $url */
        $url = $project->url;
        $bundle = Bundle::bundleWithURL($url);
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = $bundle->infoDictionary;
        $dictionary[kCFBundleNameKey] = $name;
        PropertyListSerialization::writePropertyList($dictionary, $bundle->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $this->content = json_encode($project, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::delete)]
    public function remove(): void
    {
        $body = $this->request->parsedBody;
        $objectID = $body[SQLEntity::primaryKeyName] ?? throw new BadRequestException();
        $context = $this->managedObjectContext;
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
        $project = $context->fetch($fetchRequest)->first ?? throw new NotFoundException();
        $context->delete($project);
        $context->save();
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
