<?php

namespace App\ViewControllers;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

#[Endpoint("About", transformers: [HTMLTransformer::class])]
final class AboutController extends ViewController
{
    #[Override]
    protected string $name = "About";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get]);
    }
    #[Outlet]
    public ?string $bundleName {
        get => $this->bundle->object(kCFBundleNameKey);
    }
    #[Outlet]
    public ?string $bundleVersion {
        get => $this->bundle->object(kCFBundleVersionKey);
    }
    #[Outlet]
    public ?string $bundleHumanReadableCopyright {
        get => $this->bundle->object(kCFBundleHumanReadableCopyright);
    }
    #[Outlet]
    public ?string $bundleShortVersion {
        get => $this->bundle->object(kCFBundleShortVersionStringKey);
    }
    #[Outlet]
    public ?string $websiteURL {
        get => $this->bundle->object(__PROPERTY__);
    }
    #[Outlet]
    public ?string $githubURL {
        get => $this->bundle->object(__PROPERTY__);
    }
    #[Outlet]
    public ?string $licenseURL {
        get => $this->bundle->object(__PROPERTY__);
    }

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "About Singularity";
    }
}
