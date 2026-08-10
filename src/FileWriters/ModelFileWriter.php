<?php

declare(strict_types=1);

namespace App\FileWriters;

use App\Model\Model;
use Override;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\URL;

final class ModelFileWriter extends FileWriter
{
    #[\Override]
    public string $contents {
        get => KeyedArchiver::archivedData($this->model->managedObjectModel);
    }
    private readonly Model $model;

    /**
     * @param URL $url The destination file this writer generates.
     * @param Model $model The model whose managed-object model is archived to the file.
     */
    public function __construct(URL $url, Model $model)
    {
        parent::__construct($url);
        $this->model = $model;
    }

    #[Override]
    public function save(): void
    {
        $this->model->url = $this->url;
        $context = $this->model->managedObjectContext;
        if ($context->hasChanges) {
            $context->save();
        }
        parent::save();
    }
}
