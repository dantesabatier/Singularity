<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\DelegateGenerator;
use App\EnvGenerator;
use App\IndexGenerator;
use App\InfoGenerator;
use App\JSONGenerator;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
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
use const Sabatier\Foundation\kCFBundleNameKey;

#[Endpoint("/")]
class Welcome extends ViewController
{
    /** @var ArrayClass<Project> */
    #[Outlet]
    public ArrayClass $projects {
        get {
            if (!isset($this->associatedValues[__PROPERTY__])) {
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->propertiesToFetch = new ArrayClass(["name", "creationDate", "url", "color"]);
                $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
                $this->associatedValues[__PROPERTY__] = $this->managedObjectContext->fetch($fetchRequest);
            }
            return $this->associatedValues[__PROPERTY__];
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
        $generator = new InfoGenerator($url->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $generator->save();
        $generator = new JSONGenerator($url->appendingPathComponent("composer")->appendingPathExtension("json"));
        $generator->save();
        $generator = new EnvGenerator($url->appendingPathComponent(".env"));
        $generator->save();
        $generator = new IndexGenerator($url->appendingPathComponent("index")->appendingPathExtension("php"));
        $generator->save();
        $attributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
        $fileManager = FileManager::default();
        if (!$fileManager->fileExists($url->path)) {
            $fileManager->createDirectory($url, attributes: $attributes);
        }
        $name = $url->lastPathComponent;
        $resourceURL = $url->appendingPathComponent("Resources");
        if (!$fileManager->fileExists($resourceURL->path)) {
            $fileManager->createDirectory($resourceURL, attributes: $attributes);
        }
        $bundle = Bundle::bundleWithURL($url);
        foreach ($bundle->localizations as $localization) {
            $directoryURL = $resourceURL->appendingPathComponent($localization);
            if (!$fileManager->fileExists($directoryURL->path)) {
                $fileManager->createDirectory($directoryURL, attributes: $attributes);
            }
        }
        $sourcesURL = $url->appendingPathComponent("src");
        if (!$fileManager->fileExists($sourcesURL->path)) {
            $fileManager->createDirectory($sourcesURL, attributes: $attributes);
        }
        $generator = new DelegateGenerator($sourcesURL->appendingPathComponent("Delegate")->appendingPathExtension("php"));
        $generator->save();
        $storeURL = $resourceURL->appendingPathComponent($name)->appendingPathExtension("plist");
        if (!$fileManager->fileExists($storeURL->path)) {
            PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $storeURL);
        }
        $context = $this->managedObjectContext;
        $model = new Model($context);
        $model->url = $bundle->url($name, "plist");
        $project = new Project($context);
        $project->creationDate = new Date();
        $project->name = $name;
        $project->url = $url;
        $project->model = $model;
        $context->save();
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
        $name = $body["name"] ?? throw new BadRequestException("path cannot be null");
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
    }
}
