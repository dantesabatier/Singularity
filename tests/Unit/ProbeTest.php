<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\FileWriters\ModelFileWriter;
use App\Model\EntityMap;
use App\Model\ModelMap;
use App\Tests\Support\CoreDataTestCase;

final class ProbeTest extends CoreDataTestCase
{
    public function testProbe(): void
    {
        $project = $this->makeProject();
        $model = $project->model;
        $this->makeEntity($model, "Book");
        $modelMap = new ModelMap($this->context);
        $modelMap->name = "M";
        $modelMap->model = $model;
        $this->context->save();

        $entityMap = new EntityMap($this->context);
        $entityMap->destinationEntityName = "Book";
        $entityMap->position = 0;
        $modelMap->addEntityMapsObject($entityMap);
        $this->context->save();

        fwrite(STDERR, "WITHOUT writer, hash: " . var_export($entityMap->destinationEntityVersionHash !== null, true) . "\n");

        $modelMap2 = new ModelMap($this->context);
        $modelMap2->name = "M2";
        $modelMap2->model = $model;
        $em2 = new EntityMap($this->context);
        $em2->destinationEntityName = "Book";
        $modelMap2->addEntityMapsObject($em2);
        $this->context->save();
        new ModelFileWriter($this->temporaryURL("s", "mom"), $model)->save();
        fwrite(STDERR, "AFTER writer, hash: " . var_export($em2->destinationEntityVersionHash !== null, true) . "\n");
        self::assertTrue(true);
    }
}
