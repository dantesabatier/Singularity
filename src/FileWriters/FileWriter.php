<?php

declare(strict_types=1);

namespace App\FileWriters;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\request_concrete_implementation;

abstract class FileWriter
{
    public string $name {
        get => $this->url->deletingLastPathComponent()->lastPathComponent;
    }
    public string $contents {
        get => request_concrete_implementation($this, __PROPERTY__);
    }

    /**
     * @param URL $url The destination file this writer generates.
     */
    public function __construct(public readonly URL $url)
    {
    }

    /**
     * @throws Exception
     */
    public function save(): void
    {
        FileManager::default()->createFile($this->url->path, $this->contents, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
    }
}
