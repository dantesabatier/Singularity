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
use Sabatier\Service\View;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

class Delegate extends ObjectClass implements ApplicationDelegate
{
    public static function initialize(): void
    {
        SQLCore::$debugDefault = 0;
        SQLCore::$coloredLoggingDefault = true;
        View::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false
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
