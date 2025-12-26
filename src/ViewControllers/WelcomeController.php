<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\CoreData\SQLEntity;
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
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\random_bright_color;

#[Endpoint("/")]
final class WelcomeController extends ViewController
{
    public string $name = "Welcome";
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

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function create(): void
    {
        $body = $this->request->parsedBody;
        $path = $body["path"] ?? throw new BadRequestException();
        $generateWithSecurity = $body["generateWithSecurity"] ?? true;
        $generateWithCORS = $body["generateWithCORS"] ?? true;
        $generateWithJWT = $body["generateWithJWT"] ?? true;
        $url = URL::fileURL($path);
        $name = $url->lastPathComponent;
        $context = $this->managedObjectContext;
        $model = new Model($context);
        $model->url = $url->appendingPathComponent("Resources")->appendingPathComponent($name)->appendingPathExtension("plist");
        $project = new Project($context);
        $project->creationDate = new Date();
        $project->name = $name;
        $project->url = $url;
        $project->color = random_bright_color($name);
        $project->model = $model;
        $fileWriter = new ProjectFileWriter($url, $project, $generateWithSecurity, $generateWithCORS, $generateWithJWT);
        $fileWriter->save();
        $this->data = $project;
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
