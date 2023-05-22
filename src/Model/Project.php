<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\URL;

/**
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property string|null $name
 * @property URL|null $url
 * @property Model|null $model
 */
class Project extends ManagedObject
{
}
