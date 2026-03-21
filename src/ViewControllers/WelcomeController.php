<?php

namespace App\ViewControllers;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Bundles\CreateBundleTransaction;
use App\Bundles\OpenBundleTransaction;
use App\Bundles\ProjectBundleLoader;
use App\Bundles\RenameBundleTransaction;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLDecorator;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\random_color;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

#[Endpoint("/", decorators: [HTMLDecorator::class])]
final class WelcomeController extends ViewController
{
    #[Override]
    protected string $name = "Welcome";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::delete]);
    }
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->projects)) {
                return $this->projects;
            }
            $fetchRequest = Project::fetchRequest();
            $fetchRequest->propertiesToFetch = new ArrayClass(["name", "creationDate", "url", "color"]);
            $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
            return $this->projects = $this->managedObjectContext->fetch($fetchRequest);
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
        $parameters = $this->request->parameters;
        $path = $parameters["directory"] ?? throw new BadRequestException();
        $url = URL::fileURL($path);
        $loader = new ProjectBundleLoader($url, $this->managedObjectContext);
        $transaction = new OpenBundleTransaction($loader);
        $transaction->execute();
        $this->managedObjectContext->save();
        $this->data = $transaction->project;
    }


    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function create(): void
    {
        $parameters = $this->request->parameters;
        $path = $parameters["directory"] ?? throw new BadRequestException();
        $options = new BundleGenerationOptions($parameters["generateWithSecurity"] ?? false, $parameters["generateWithCORS"] ?? false, $parameters["generateWithJWT"] ?? false);
        $url = URL::fileURL($path);
        $project = $this->createProjectFromURL($url);
        $scaffolder = new BundleScaffolder($url, $project, $options);
        $transaction = new CreateBundleTransaction($scaffolder, $url);
        $transaction->execute();
        $this->managedObjectContext->save();
        $this->data = $project;
    }


    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::patch, decorators: [JSONDecorator::class])]
    public function rename(): void
    {
        $parameters = $this->request->parameters;
        $newName = $parameters["name"] ?? throw new BadRequestException();
        $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        $project = $this->projectWithID($objectID);
        $transaction = new RenameBundleTransaction($project, $newName);
        $transaction->execute();
        $this->managedObjectContext->save();
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::delete)]
    public function remove(): void
    {
        $objectID = $this->request->parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
        $context = $this->managedObjectContext;
        $project = $this->projectWithID($objectID);
        $context->delete($project);
        $context->save();
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
