<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Project;
use App\ViewControllers\Editor;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\SQLCore;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\PersistentSpace;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\full_user_name;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;

class Delegate extends ObjectClass implements ApplicationDelegate
{
    public static function initialize(): void
    {
        SQLCore::$debugDefault = 0;
        SQLCore::$coloredLoggingDefault = HAS_ESCAPE_SEQUENCES;
        ViewController::$rendererClass = LatteRenderer::class;
        UserDefaults::standard()->register(new Dictionary([
            PersistentHistoryTrackingKey => false,
            PersistentStoreRemoteChangeNotificationPostOptionKey => false,
            AutomaticallyDeleteProjectFolders => false,
            AutomaticallySaveModel => false,
            CompanyNameKey => full_user_name()
        ]));
    }

    public function applicationWillFinishLaunching(Application $application): void
    {
        $application->isProtectedContentAvailable = true;
        NotificationCenter::default()->addObserverForName(ManagedObjectContext::didSaveObjectsNotification, null, function (Notification $notification) use ($application): void {
            if (UserDefaults::standard()->bool(AutomaticallySaveModel) && $application->firstResponder instanceof PersistentSpace && ($referer = $application->firstResponder->request->valueForHttpHeaderField("Referer"))) {
                $components = new URLComponents($referer);
                $referenceObject = $components->queryItems?->first(fn(URLQueryItem $item): bool => $item->name === "project")?->value;
                if (is_numeric($referenceObject)) {
                    /** @var ManagedObjectContext $context */
                    $context = $notification->object;
                    $fetchRequest = Project::fetchRequest();
                    $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
                    $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                        "name" => AttributeType::string,
                        "url" => AttributeType::uri,
                        "model" => [
                            "url" => AttributeType::uri
                        ]
                    ]);
                    $editor = new Editor();
                    $editor->project = $context->fetch($fetchRequest)->first;
                    $editor->save();
                }
            }
        });
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
