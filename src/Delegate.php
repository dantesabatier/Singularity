<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use Override;
use Sabatier\CoreData\SQLCore;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\PublicAccessPolicy;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\full_user_name;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

final class Delegate extends ObjectClass implements ApplicationDelegate
{
    #[Override]
    public static function initialize(): void
    {
        SQLCore::$debugDefault = 0;
        SQLCore::$coloredLoggingDefault = true;
        ViewController::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false,
            AutomaticallyDeleteProjectFoldersPreferencesKey => false,
            CompanyNamePreferencesKey => full_user_name()
        ]));
    }

    #[Override]
    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->accessPolicy = new PublicAccessPolicy();
    }

    #[Override]
    public function applicationDidFinishLaunching(Application $application): void
    {
    }

    #[Override]
    public function applicationWillTerminate(Application $application): void
    {
    }
}
