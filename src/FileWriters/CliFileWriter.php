<?php

declare(strict_types=1);

namespace App\FileWriters;

use Override;
use Sabatier\Service\Jobs\JobRunner;

final class CliFileWriter extends FileWriter
{
    #[Override]
    public string $contents {
        get {
            $content = "<?php\n";
            $content .= "\n";
            $content .= "declare(strict_types=1);\n";
            $content .= "\n";
            $content .= "require_once __DIR__ . \"/vendor/autoload.php\";\n";
            $content .= "\n";
            $content .= "use " . JobRunner::class . ";\n";
            $content .= "\n";
            return $content . "new JobRunner()->run();\n";
        }
    }
}
