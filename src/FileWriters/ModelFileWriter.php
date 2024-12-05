<?php

namespace App\FileWriters;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\PropertyListSerialization;

class ModelFileWriter extends FileWriter
{
    public function save(): void
    {
        PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $this->url);
    }
}
