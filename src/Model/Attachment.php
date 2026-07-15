<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

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
    private bool $isFileURLResolved = false;
    private(set) ?URL $fileURL {
        get {
            if ($this->isFileURLResolved) {
                return $this->fileURL;
            }
            $this->isFileURLResolved = true;
            if (!($url = $this->url)) {
                return $this->fileURL = null;
            }
            return $this->fileURL = FileManager::default()->documentRootDirectory->appendingPathComponent($url->path);
        }
    }
    public string $dataURL {
        get {
            if (!($url = $this->fileURL)) {
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
        if (!($url = $this->fileURL)) {
            return;
        }
        if (!FileManager::default()->fileExists($url->path)) {
            return;
        }
        try {
            FileManager::default()->removeItem($url);
        } catch (Exception) {
        }
    }
}
