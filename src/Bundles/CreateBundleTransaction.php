<?php

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
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class CreateBundleTransaction implements CompensableTransaction
{
    private bool $executed = false;

    public function __construct(private readonly URL $bundleURL, private readonly Project $project, private readonly BundleGenerationOptions $options)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        try {
            $this->createDirectories();
            $this->createBaseFiles();
            $this->createModelIfNeeded();
            $this->executed = true;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function rollback(): void
    {
        if (!$this->executed) {
            FileManager::default()->removeItem($this->bundleURL);
        }
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
            $modelURL = $this->bundleURL->appendingPathComponent("Resources")->appendingPathComponent($this->bundleURL->lastPathComponent)->appendingPathExtension("plist");
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
