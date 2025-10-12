<?php

namespace App\FileWriters;

use Sabatier\Service\Application;

final class IndexFileWriter extends FileWriter
{
    public string $contents {
        get {
            $content = "<?php\n";
            $content .= "\n";
            $content .= "require_once __DIR__ . \"/vendor/autoload.php\";\n";
            $content .= "\n";
            $content .= "use " . Application::class . ";\n";
            $content .= "\n";
            return $content . "Application::shared()->run();\n";
        }
    }
}
