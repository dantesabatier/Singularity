<?php

namespace App\FileWriters;

use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class ProjectFileWriter extends FileWriter
{
    private(set) Project $project;
    private bool $isGeneratedWithSecurity;
    private bool $isGeneratedWithCORS;
    private bool $isGeneratedWithJWT;

    public function __construct(URL $url, Project $project, bool $isGeneratedWithSecurity, bool $generateWithCORS, bool $generateWithJWT)
    {
        parent::__construct($url);
        $this->project = $project;
        $this->isGeneratedWithSecurity = $isGeneratedWithSecurity;
        $this->isGeneratedWithCORS = $generateWithCORS;
        $this->isGeneratedWithJWT = $generateWithJWT;
    }

    #[Override]
    public function save(): void
    {
        $this->createDirectories();
        $this->createFileIfNotExists(new PlistFileWriter($this->url->appendingPathComponent("Info")->appendingPathExtension("plist")));
        $this->createFileIfNotExists(new ComposerJsonFileWriter($this->url->appendingPathComponent("composer")->appendingPathExtension("json")));
        $this->createFileIfNotExists(new DotEnvFileWriter($this->url->appendingPathComponent(".env"), $this->isGeneratedWithCORS, $this->isGeneratedWithJWT));
        $this->createFileIfNotExists(new IndexFileWriter($this->url->appendingPathComponent("index")->appendingPathExtension("php")));
        $this->createFileIfNotExists(new DelegateFileWriter($this->url->appendingPathComponent("src")->appendingPathComponent("Delegate")->appendingPathExtension("php"), $this->isGeneratedWithSecurity));
        $this->createModelFile();
        $this->saveProject();
    }

    /**
     * @throws Exception
     */
    private function createDirectories(): void
    {
        $fileManager = FileManager::default();
        $directoryURLs = new ArrayClass([$this->url, $this->url->appendingPathComponent("Resources"), $this->url->appendingPathComponent("Resources")->appendingPathComponent("en"), $this->url->appendingPathComponent("src")]);
        $attributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
        foreach ($directoryURLs as $dirURL) {
            if (!$fileManager->fileExists($dirURL->path)) {
                $fileManager->createDirectory($dirURL, true, $attributes);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createFileIfNotExists(FileWriter $writer): void
    {
        $fileManager = FileManager::default();
        if (!$fileManager->fileExists($writer->url->path)) {
            $writer->save();
        }
    }

    /**
     * @throws Exception
     */
    private function createModelFile(): void
    {
        $model = $this->project->model;
        if ($model && $model->isInserted) {
            $fileURL = $this->url->appendingPathComponent("Resources")->appendingPathComponent($this->url->lastPathComponent)->appendingPathExtension("plist");
            $fileWriter = new ModelFileWriter($fileURL, $model);
            $fileWriter->save();
        }
    }

    /**
     * @throws Exception
     */
    public function saveProject(): void
    {
        $this->project->managedObjectContext->save();
    }
}
