<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use Override;
use Sabatier\CoreData\MergePolicy;
use Sabatier\CoreData\SQLCore;
use Sabatier\CoreData\SQLDebugLevel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\PublicAccessPolicy;
use Sabatier\Service\ViewController;
use Throwable;
use function Sabatier\Foundation\full_user_name;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

final class Delegate extends ObjectClass implements ApplicationDelegate
{
    #[Override]
    public static function initialize(): void
    {
        SQLCore::$debugLevel = SQLDebugLevel::none;
        SQLCore::$debugColorOutputDefault = true;
        ViewController::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false,
            AutomaticallyDeleteProjectFoldersPreferencesKey => false,
            CompanyNamePreferencesKey => full_user_name(),
            EditorSelectedViewPreferencesKey => EditorTableViewValue,
            EditorSplitSizesPreferencesKey => new ArrayClass([20, 60, 20]),
            ExportIncludeDataPreferencesKey => true,
            ExportIncludeCommentsPreferencesKey => false,
        ]));
    }

    #[Override]
    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->accessPolicy = new PublicAccessPolicy();
        $application->persistentContainer->viewContext->mergePolicy = MergePolicy::mergeByPropertyObjectTrump();
    }

    #[Override]
    public function applicationDidFinishLaunching(Application $application): void
    {
    }

    #[Override]
    public function applicationWillTerminate(Application $application): void
    {
    }

    #[Override]
    public function applicationDidCrash(Application $application, Throwable $throwable): void
    {
    }
}
