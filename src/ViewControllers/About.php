<?php

namespace App\ViewControllers;

use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

#[Endpoint]
class About extends ViewController
{
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
}
