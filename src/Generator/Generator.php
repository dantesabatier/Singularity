<?php

namespace App\Generator;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

abstract class Generator
{
    public string $name {
        get => $this->url->deletingLastPathComponent()->lastPathComponent;
    }
    public abstract string $contents {
        get;
    }

    public function __construct(public URL $url)
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
