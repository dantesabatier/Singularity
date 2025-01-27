<?php

namespace App\FileWriters;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\PropertyListSerialization;

class ModelFileWriter extends FileWriter
{
    #[Override]
    public function save(): void
    {
        if (FileManager::default()->fileExists($this->url->path)) {
            return;
        }
        PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $this->url);
    }
}
