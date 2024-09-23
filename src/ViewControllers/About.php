<?php

namespace App\ViewControllers;

use Override;
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
    public ?string $bundleName = null;
    #[Outlet]
    public ?string $bundleVersion = null;
    #[Outlet]
    public ?string $bundleHumanReadableCopyright = null;
    #[Outlet]
    public ?string $bundleShortVersion = null;

    #[Override]
    public function viewWillLoad(): void
    {
        $this->bundleName = $this->bundle->object(kCFBundleNameKey);
        $this->bundleVersion = $this->bundle->object(kCFBundleVersionKey);
        $this->bundleHumanReadableCopyright = $this->bundle->object(kCFBundleHumanReadableCopyright);
        $this->bundleShortVersion = $this->bundle->object(kCFBundleShortVersionStringKey);
    }
}
