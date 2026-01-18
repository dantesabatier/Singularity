<?php

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\random_color;

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
}
