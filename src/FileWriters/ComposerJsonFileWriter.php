<?php

namespace App\FileWriters;

final class ComposerJsonFileWriter extends FileWriter
{
    public string $contents {
        get {
            $name = $this->name;
            return json_encode([
                "name" => sprintf("vendor/%s", strtolower($name)),
                "description" => $name,
                "version" => "1.0.0",
                "license" => "MIT",
                "keywords" => [
                    $name
                ],
                "require" => [
                    "php" => sprintf("^%s", PHP_VERSION),
                    "ext-curl" => "*",
                    "ext-dom" => "*",
                    "ext-gettext" => "*",
                    "ext-json" => "*",
                    "ext-mbstring" => "*",
                    "sabatier/foundation" => "dev-master",
                    "sabatier/coredata" => "dev-master",
                    "sabatier/service" => "dev-master"
                ],
                "config" => [
                    "platform" => [
                        "ext-pcntl" => PHP_VERSION,
                        "ext-posix" => PHP_VERSION,
                        "ext-gd" => PHP_VERSION,
                        "ext-intl" => PHP_VERSION,
                        "ext-fileinfo" => PHP_VERSION
                    ]
                ],
                "autoload" => [
                    "psr-4" => [
                        "App\\" => "src"
                    ]
                ],
                "repositories" => array_map(fn(string $name): array => ["type" => "path", "url" => "../Sabatier/$name"], ["Foundation", "CoreData", "Service"])
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
}
