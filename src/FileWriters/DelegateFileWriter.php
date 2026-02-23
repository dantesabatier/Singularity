<?php

namespace App\FileWriters;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\PublicAccessPolicy;
use Throwable;

final class DelegateFileWriter extends FileWriter
{
    #[Override]
    public string $name {
        get => $this->url->deletingPathExtension()->lastPathComponent;
    }
    #[Override]
    public string $contents {
        get {
            $uses = new ArrayClass([
                "use " . ObjectClass::class . ";",
                "use " . Application::class . ";",
                "use " . ApplicationDelegate::class . ";",
                "use " . PublicAccessPolicy::class . ";",
                "use " . Throwable::class . ";",
            ]);
            $content = "<?php\n";
            $content .= "\n";
            $content .= "namespace App;\n";
            $content .= "\n";
            $content .= $uses->sort()->join("\n");
            $content .= "\n";
            $content .= "\n";
            $content .= "class $this->name extends ObjectClass implements ApplicationDelegate\n";
            $content .= "{\n";
            $content .= "    public static function initialize(): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationWillFinishLaunching(Application \$application): void\n";
            $content .= "    {\n";
            if (!$this->isGeneratedWithSecurity) {
                $content .= "        \$application->accessPolicy = new PublicAccessPolicy();\n";
            }
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationDidFinishLaunching(Application \$application): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationWillTerminate(Application \$application): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationDidCrash(Application \$application, Throwable \$throwable): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            return "$content}\n";
        }
    }
    private readonly bool $isGeneratedWithSecurity;

    public function __construct(URL $url, bool $isGeneratedWithSecurity)
    {
        parent::__construct($url);
        $this->isGeneratedWithSecurity = $isGeneratedWithSecurity;
    }
}
