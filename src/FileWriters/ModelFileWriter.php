<?php

namespace App\FileWriters;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\PropertyListSerialization;

class ModelFileWriter extends FileWriter
{
    #[Override]
    public function save(): void
    {
        PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $this->url);
    }
}
