<?php

namespace App\ViewControllers;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;

#[Endpoint("Help", transformers: [HTMLTransformer::class])]
final class HelpController extends ViewController
{
    #[Override]
    protected string $name = "Help";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get]);
    }
    /** @var ArrayClass<Dictionary<mixed>> */
    #[Outlet]
    private(set) ArrayClass $sections {
        get => $this->sections ??= new ArrayClass([
            new Dictionary([
                "title" => "Getting Started",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "welcome", "title" => "Welcome to Singularity", "icon" => "waving_hand"]),
                    new Dictionary(["anchor" => "create-project", "title" => "Create Your First Project", "icon" => "create_new_folder"]),
                ]),
            ]),
            new Dictionary([
                "title" => "Designing Your Model",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "entities", "title" => "Working with Entities", "icon" => "table"]),
                    new Dictionary(["anchor" => "attributes", "title" => "Adding Attributes", "icon" => "label"]),
                    new Dictionary(["anchor" => "relationships", "title" => "Adding Relationships", "icon" => "compare_arrows"]),
                ]),
            ]),
            new Dictionary([
                "title" => "Generating Code",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "generate", "title" => "Generating Your Project", "icon" => "code"]),
                ]),
            ]),
        ]);
    }
    #[Outlet]
    public string $anchor {
        get => $this->anchor ??= (string)($this->request->parameters["anchor"] ?? "welcome");
    }

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "Singularity Help";
    }
}
