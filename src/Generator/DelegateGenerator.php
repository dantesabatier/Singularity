<?php

namespace App\Generator;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;

class DelegateGenerator extends Generator
{
    public string $contents {
        get {
            $uses = new ArrayClass([
                "use " . Error::class . ";",
                "use " . ObjectClass::class . ";",
                "use " . Application::class . ";",
                "use " . ApplicationDelegate::class . ";",
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
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationDidFinishLaunching(Application \$application): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationWillPresentError(Application \$application, Error \$error): Error\n";
            $content .= "    {\n";
            $content .= "        return \$error;\n";
            $content .= "    }\n";
            $content .= "\n";
            $content .= "    public function applicationWillTerminate(Application \$application): void\n";
            $content .= "    {\n";
            $content .= "    }\n";
            return "$content}\n";
        }
    }
}
