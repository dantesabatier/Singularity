<?php

declare(strict_types=1);

namespace App;

use App\LLM\Provider;
use Override;
use Sabatier\CoreData\MergePolicy;
use Sabatier\CoreData\PersistentStore;
use Sabatier\CoreData\RedisRowCache;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\PublicAccessPolicy;
use Sabatier\Service\RedisIdempotencyStore;
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
        PersistentStore::$rowCacheClass = RedisRowCache::class;
        ViewController::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false,
            AutomaticallyDeleteProjectFoldersPreferencesKey => false,
            CompanyNamePreferencesKey => full_user_name(),
            EditorSelectedViewPreferencesKey => EditorTableViewValue,
            EditorSplitSizesPreferencesKey => new ArrayClass([20, 60, 20]),
            EditorCopilotEnabledPreferencesKey => false,
            ExportIncludeDataPreferencesKey => true,
            ExportIncludeCommentsPreferencesKey => false,
            LLMProviderPreferencesKey => "anthropic",
            LLMModelPreferencesKey => "claude-opus-4-8",
            LLMProvidersPreferencesKey => new ArrayClass([Provider::anthropic()->dictionaryRepresentation]),
        ]));
    }

    #[Override]
    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->accessPolicy = new PublicAccessPolicy();
        $application->idempotencyStore = new RedisIdempotencyStore();
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
