<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/**
 * @property string $name
 * @property URL|null $url
 * @property Message|null $message
 */
final class Attachment extends ManagedObject
{
    public string $dataURL {
        get {
            if (!($url = $this->url)) {
                return "";
            }
            if (!($data = FileManager::default()->contents($url->path))) {
                return "";
            }
            $mimeType = URLFileTypeMappings::shared()->mimeType($url->pathExtension) ?? "application/octet-stream";
            return "data:$mimeType;base64," . base64_encode($data);
        }
    }

    #[Override]
    public function prepareForDeletion(): void
    {
        if (!($url = $this->url)) {
            return;
        }
        $url = new URL($url->path, FileManager::default()->documentRootDirectory);
        if (!FileManager::default()->fileExists($url->path)) {
            return;
        }
        try {
            FileManager::default()->removeItem($url);
        } catch (Exception) {
        }
    }
}
