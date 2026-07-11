<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\ComposerJsonFileWriter;
use App\FileWriters\DelegateFileWriter;
use App\FileWriters\DotEnvFileWriter;
use App\FileWriters\FileWriter;
use App\FileWriters\IndexFileWriter;
use App\FileWriters\ModelFileWriter;
use App\FileWriters\PlistFileWriter;
use App\Model\Project;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final readonly class BundleScaffolder
{
    public function __construct(private URL $bundleURL, private Project $project, private BundleGenerationOptions $options)
    {
    }

    /**
     * @throws Exception
     */
    public function scaffold(): void
    {
        $this->createDirectories();
        $this->createBaseFiles();
        $this->createModelIfNeeded();
    }

    /**
     * @throws Exception
     */
    private function createDirectories(): void
    {
        $fileManager = FileManager::default();
        $directories = new ArrayClass([$this->bundleURL, $this->bundleURL->appendingPathComponent("Resources"), $this->bundleURL->appendingPathComponent("Resources")->appendingPathComponent("en"), $this->bundleURL->appendingPathComponent("src")]);
        $attributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
        foreach ($directories as $dirURL) {
            if (!$fileManager->fileExists($dirURL->path)) {
                $fileManager->createDirectory($dirURL, true, $attributes);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createBaseFiles(): void
    {
        $this->createIfMissing(new PlistFileWriter($this->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist")));
        $this->createIfMissing(new ComposerJsonFileWriter($this->bundleURL->appendingPathComponent("composer")->appendingPathExtension("json")));
        $this->createIfMissing(new DotEnvFileWriter($this->bundleURL->appendingPathComponent(".env"), $this->options->withCORS, $this->options->withJWT));
        $this->createIfMissing(new IndexFileWriter($this->bundleURL->appendingPathComponent("index")->appendingPathExtension("php")));
        $this->createIfMissing(new DelegateFileWriter($this->bundleURL->appendingPathComponent("src")->appendingPathComponent("Delegate")->appendingPathExtension("php"), $this->options->withSecurity));
    }

    /**
     * @throws Exception
     */
    private function createModelIfNeeded(): void
    {
        $model = $this->project->model;
        if ($model && $model->isInserted) {
            $modelURL = $this->bundleURL->appendingPathComponent("Resources")->appendingPathComponent($this->bundleURL->lastPathComponent)->appendingPathExtension("mom");
            new ModelFileWriter($modelURL, $model)->save();
        }
    }

    /**
     * @throws Exception
     */
    private function createIfMissing(FileWriter $writer): void
    {
        $path = $writer->url->path;
        if (!FileManager::default()->fileExists($path)) {
            $writer->save();
        }
    }
}
