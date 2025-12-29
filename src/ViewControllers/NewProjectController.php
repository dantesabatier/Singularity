<?php

namespace App\ViewControllers;

use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;

#[Endpoint("NewProject")]
final class NewProjectController extends ViewController
{
    public string $name = "NewProject";
    #[Outlet]
    public ?string $path = null;
    #[Outlet]
    public bool $generateWithSecurity = true;
    #[Outlet]
    public bool $generateWithCORS = true;
    #[Outlet]
    public bool $generateWithJWT = true;
}
