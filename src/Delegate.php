<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use Sabatier\CoreData\SQLCore;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\full_user_name;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

class Delegate extends ObjectClass implements ApplicationDelegate
{
    public static function initialize(): void
    {
        SQLCore::$debugDefault = 3;
        SQLCore::$coloredLoggingDefault = true;
        ViewController::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false,
            CompanyNameKey => full_user_name()
        ]));
    }

    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->isProtectedContentAvailable = true;
    }

    public function applicationDidFinishLaunching(Application $application): void
    {
    }

    public function applicationWillPresentError(Application $application, Error $error): Error
    {
        return $error;
    }

    public function applicationWillTerminate(Application $application): void
    {
    }
}
