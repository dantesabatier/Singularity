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
                    new Dictionary(["anchor" => "editor", "title" => "Navigating the Editor", "icon" => "dashboard"]),
                    new Dictionary(["anchor" => "entities", "title" => "Working with Entities", "icon" => "table"]),
                    new Dictionary(["anchor" => "attributes", "title" => "Adding Attributes", "icon" => "label"]),
                    new Dictionary(["anchor" => "relationships", "title" => "Adding Relationships", "icon" => "compare_arrows"]),
                    new Dictionary(["anchor" => "fetched-properties", "title" => "Fetched Properties", "icon" => "move_to_inbox"]),
                    new Dictionary(["anchor" => "composite-types", "title" => "Composite Types", "icon" => "account_tree"]),
                ]),
            ]),
            new Dictionary([
                "title" => "Querying and Storage",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "fetch-requests", "title" => "Fetch Requests", "icon" => "search"]),
                    new Dictionary(["anchor" => "fetch-indexes", "title" => "Fetch Indexes", "icon" => "bolt"]),
                    new Dictionary(["anchor" => "configurations", "title" => "Configurations", "icon" => "settings"]),
                ]),
            ]),
            new Dictionary([
                "title" => "Security and Output",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "access-control", "title" => "Access Control", "icon" => "verified_user"]),
                    new Dictionary(["anchor" => "generate", "title" => "Generating Your Project", "icon" => "code"]),
                    new Dictionary(["anchor" => "sql-viewer", "title" => "SQL Schema Viewer", "icon" => "data_object"]),
                ]),
            ]),
            new Dictionary([
                "title" => "Application Tools",
                "pages" => new ArrayClass([
                    new Dictionary(["anchor" => "preferences", "title" => "Preferences", "icon" => "tune"]),
                    new Dictionary(["anchor" => "copilot", "title" => "AI Copilot", "icon" => "auto_awesome"]),
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
