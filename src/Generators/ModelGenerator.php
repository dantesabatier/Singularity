<?php

namespace App\Generators;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\PropertyListSerialization;

class ModelGenerator extends Generator
{
    public function save(): void
    {
        PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $this->url);
    }
}
