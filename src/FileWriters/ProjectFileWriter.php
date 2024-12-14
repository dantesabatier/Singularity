<?php

namespace App\FileWriters;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

class ProjectFileWriter extends FileWriter
{
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
        $fileWriter = new InfoFileWriter($url->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $fileWriter->save();
        $fileWriter = new JSONFileWriter($url->appendingPathComponent("composer")->appendingPathExtension("json"));
        $fileWriter->save();
        $fileWriter = new EnvFileWriter($url->appendingPathComponent(".env"));
        $fileWriter->save();
        $fileWriter = new IndexFileWriter($url->appendingPathComponent("index")->appendingPathExtension("php"));
        $fileWriter->save();
        $fileWriter = new DelegateFileWriter($sourcesURL->appendingPathComponent("Delegate")->appendingPathExtension("php"));
        $fileWriter->save();
        $fileWriter = new ModelFileWriter($resourceURL->appendingPathComponent($name)->appendingPathExtension("plist"));
        $fileWriter->save();
    }
}
