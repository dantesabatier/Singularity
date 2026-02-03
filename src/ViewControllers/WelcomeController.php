<?php

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
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
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\random_color;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;
use const Sabatier\CoreData\ModelURLOption;
use const Sabatier\Foundation\kCFBundleNameKey;

#[Endpoint("/")]
final class WelcomeController extends ViewController
{
    public string $name = "Welcome";
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::delete]);
    }
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        /**
         * @throws Exception
         */
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
    #[Outlet]
    public ?string $directory = null;
    #[Outlet]
    public bool $generateWithSecurity = true;
    #[Outlet]
    public bool $generateWithCORS = true;
    #[Outlet]
    public bool $generateWithJWT = true;

    /**
     * @throws Exception
     */
    private function projectWithID(int $objectID): Project
    {
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ManagedObjectObjectIDKey), Expression::expressionForConstantValue($objectID));
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException();

    }

    private function createProjectFromURL(URL $url): Project
    {
        $name = $url->lastPathComponent;
        $context = $this->managedObjectContext;
        $model = new Model($context);
        $model->url = $url->appendingPathComponent("Resources")->appendingPathComponent($name)->appendingPathExtension("plist");
        $project = new Project($context);
        $project->creationDate = new Date();
        $project->name = $name;
        $project->url = $url;
        $project->color = random_color($name);
        $project->model = $model;
        return $project;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function open(): void
    {
        $body = $this->request->parsedBody;
        $path = $body["directory"] ?? throw new BadRequestException();
        $url = URL::fileURL($path);
        $project = $this->createProjectFromURL($url);
        $fileWriter = new ProjectFileWriter($url, $project);
        $fileWriter->save();
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function create(): void
    {
        $body = $this->request->parsedBody;
        $path = $body["directory"] ?? throw new BadRequestException();
        $generateWithSecurity = $body["generateWithSecurity"] ?? false;
        $generateWithCORS = $body["generateWithCORS"] ?? false;
        $generateWithJWT = $body["generateWithJWT"] ?? false;
        $url = URL::fileURL($path);
        $project = $this->createProjectFromURL($url);
        $fileWriter = new ProjectFileWriter($url, $project, $generateWithSecurity, $generateWithCORS, $generateWithJWT);
        $fileWriter->save();
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::patch, decorators: [JSONDecorator::class])]
    public function rename(): void
    {
        $body = $this->request->parsedBody;
        $newName = $body["name"] ?? throw new BadRequestException();
        $objectID = $body[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        $context = $this->managedObjectContext;
        $project = $this->projectWithID($objectID);
        /** @var URL $url */
        $url = $project->url;
        $bundle = Bundle::bundleWithURL($url);
        /** @var string $oldName */
        $oldName = $bundle->object(kCFBundleNameKey);
        if ($oldName === $newName) {
            return;
        }
        $autoloadPath = $bundle->bundleURL->appendingPathComponent("vendor")->appendingPathComponent("autoload")->appendingPathExtension("php")->path;
        if (FileManager::default()->fileExists($autoloadPath)) {
            require_once $autoloadPath;
        }
        if (!($destinationModelURL = $bundle->resourceURL?->appendingPathComponent($newName)?->appendingPathExtension("plist"))) {
            return;
        }
        if (!($sourceModelURL = $bundle->url($oldName))) {
            return;
        }
        FileManager::default()->copyItem($sourceModelURL, $destinationModelURL);
        $managedObjectModel = new ManagedObjectModel($sourceModelURL);
        $coordinator = new PersistentStoreCoordinator($managedObjectModel);
        $sourceURL = new URL("sql://$oldName");
        $sourceOptions = new Dictionary([ModelURLOption => $sourceModelURL]);
        $destinationURL = new URL("sql://$newName");
        $destinationOptions = new Dictionary([ModelURLOption => $destinationModelURL]);
        $coordinator->replacePersistentStore($destinationURL, $destinationOptions, $sourceURL, $sourceOptions, PersistentStoreType::sql);
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = $bundle->infoDictionary;
        $dictionary[kCFBundleNameKey] = $newName;
        PropertyListSerialization::writePropertyList($dictionary, $bundle->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $project->name = $newName;
        $context->save();
        FileManager::default()->removeItem($sourceModelURL);
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::delete)]
    public function remove(): void
    {
        $objectID = $this->request->parsedBody[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        $context = $this->managedObjectContext;
        $project = $this->projectWithID($objectID);
        $context->delete($project);
        $context->save();
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
