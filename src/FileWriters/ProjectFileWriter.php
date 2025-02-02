<?php

namespace App\FileWriters;

use App\Model\Project;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

class ProjectFileWriter extends FileWriter
{
    private(set) Project $project;

    public function __construct(URL $url, Project $project)
    {
        parent::__construct($url);
        $this->project = $project;
    }

    #[Override]
    public function save(): void
    {
        $url = $this->url;
        /** @var ArrayClass<URL> $directoryURLs */
        $directoryURLs = new ArrayClass([$url]);
        $resourceURL = $url->appendingPathComponent("Resources");
        $directoryURLs[] = $resourceURL;
        $directoryURLs[] = $resourceURL->appendingPathComponent("en");
        $sourcesURL = $url->appendingPathComponent("src");
        $directoryURLs[] = $sourcesURL;
        $fileManager = FileManager::default();
        $attributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
        foreach ($directoryURLs as $directoryURL) {
            $directory = $directoryURL->path;
            if (!$fileManager->fileExists($directory)) {
                $fileManager->createDirectory($directoryURL, true, $attributes);
            }
        }
        $name = $url->lastPathComponent;
        $fileURL = $url->appendingPathComponent("Info")->appendingPathExtension("plist");
        if (!$fileManager->fileExists($fileURL->path)) {
            $fileWriter = new InfoFileWriter($fileURL);
            $fileWriter->save();
        }
        $fileURL = $url->appendingPathComponent("composer")->appendingPathExtension("json");
        if (!$fileManager->fileExists($fileURL->path)) {
            $fileWriter = new JSONFileWriter($fileURL);
            $fileWriter->save();
        }
        $fileURL = $url->appendingPathComponent(".env");
        if (!$fileManager->fileExists($fileURL->path)) {
            $fileWriter = new EnvFileWriter($fileURL);
            $fileWriter->save();
        }
        $fileURL = $url->appendingPathComponent("index")->appendingPathExtension("php");
        if (!$fileManager->fileExists($fileURL->path)) {
            $fileWriter = new IndexFileWriter($fileURL);
            $fileWriter->save();
        }
        $fileURL = $sourcesURL->appendingPathComponent("Delegate")->appendingPathExtension("php");
        if (!$fileManager->fileExists($fileURL->path)) {
            $fileWriter = new DelegateFileWriter($fileURL);
            $fileWriter->save();
        }
        $fileURL = $resourceURL->appendingPathComponent($name)->appendingPathExtension("plist");
        if ($model = $this->project->model) {
            $fileWriter = new ModelFileWriter($fileURL, $model);
            $fileWriter->save();
        }
        $this->project->managedObjectContext->save();
    }
}
