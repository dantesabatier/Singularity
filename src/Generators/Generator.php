<?php

namespace App\Generators;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\request_concrete_implementation;

abstract class Generator
{
    public string $name {
        get => $this->url->deletingLastPathComponent()->lastPathComponent;
    }
    public string $contents {
        get => request_concrete_implementation($this, __PROPERTY__);
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
