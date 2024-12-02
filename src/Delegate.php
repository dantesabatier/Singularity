<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Project;
use App\ViewControllers\Editor;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\SQLCore;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\full_user_name;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

class Delegate extends ObjectClass implements ApplicationDelegate
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
            AutomaticallyDeleteProjectFolders => false,
            AutomaticallySaveModel => false,
            CompanyNameKey => full_user_name()
        ]));
    }

    #[Override]
    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->accessManager->isProtectedContentAvailable = true;
        NotificationCenter::default()->addObserverForName(ManagedObjectContext::didSaveObjectsNotification, null, function (Notification $notification) use ($application): void {
            if (!UserDefaults::standard()->bool(AutomaticallySaveModel) || !($referer = $application->request->valueForHttpHeaderField("Referer"))) {
                return;
            }
            $components = new URLComponents($referer);
            if (!($referenceObject = $components->queryItems?->first(fn(URLQueryItem $item): bool => $item->name === "project")?->value)) {
                return;
            }
            /** @var ManagedObjectContext $context */
            $context = $notification->object;
            $fetchRequest = Project::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["objectID", $referenceObject]));
            $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                "name" => AttributeType::string,
                "url" => AttributeType::uri,
                "model" => [
                    "url" => AttributeType::uri
                ]
            ]);
            if (!($project = $context->fetch($fetchRequest)->first)) {
                return;
            }
            $editor = new Editor();
            $editor->project = $project;
            $editor->save();
        });
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
