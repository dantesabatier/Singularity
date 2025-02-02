<?php

namespace App\FileWriters;

use App\Model\Model;
use Override;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;

class ModelFileWriter extends FileWriter
{
    private(set) Model $model;

    public function __construct(URL $url, Model $model)
    {
        parent::__construct($url);
        $this->model = $model;
    }

    #[Override]
    public function save(): void
    {
        PropertyListSerialization::writePropertyList($this->model->dictionaryRepresentation, $this->url);
        $this->model->url = $this->url;
        $this->model->managedObjectContext->save();
    }
}
