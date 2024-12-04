<?php

namespace App\Generators;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

class ProjectGenerator extends Generator
{
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
        $generator = new InfoGenerator($url->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $generator->save();
        $generator = new JSONGenerator($url->appendingPathComponent("composer")->appendingPathExtension("json"));
        $generator->save();
        $generator = new EnvGenerator($url->appendingPathComponent(".env"));
        $generator->save();
        $generator = new IndexGenerator($url->appendingPathComponent("index")->appendingPathExtension("php"));
        $generator->save();
        $generator = new DelegateGenerator($sourcesURL->appendingPathComponent("Delegate")->appendingPathExtension("php"));
        $generator->save();
        $generator = new ModelGenerator($resourceURL->appendingPathComponent($name)->appendingPathExtension("plist"));
        $generator->save();
    }
}
