<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\PropertyMapping;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;

/**
 * How one property of a destination entity gets its value during a migration.
 *
 * A property map holds a single name — the destination property's — plus the expression that produces
 * its value. The source property is named inside that expression (`$source.note`), which is why there
 * is no second name here.
 *
 * @property string $name
 * @property string|null $valueExpressionFormat
 * @property int<0, max> $position
 * @property Dictionary<mixed>|null $userInfo
 * @property EntityMap|null $attributeEntityMap
 * @property EntityMap|null $relationshipEntityMap
 */
final class PropertyMap extends ManagedObject
{
    /** @var EntityMap|null The entity map this property map belongs to, whichever of its two collections holds it. */
    public ?EntityMap $entityMap {
        get => $this->attributeEntityMap ?? $this->relationshipEntityMap;
    }
    /** @var PropertyMapping The mapping the engine reads once the map is archived. */
    private(set) PropertyMapping $propertyMapping {
        get {
            if (isset($this->propertyMapping)) {
                return $this->propertyMapping;
            }
            $propertyMapping = new PropertyMapping($this->name, $this->valueExpression);
            $propertyMapping->userInfo = $this->userInfo;
            return $this->propertyMapping = $propertyMapping;
        }
    }
    /** @var Expression|null The expression the format string spells out, or null when there is no format to parse. */
    public ?Expression $valueExpression {
        get => ($format = $this->valueExpressionFormat) === null || $format === "" ? null : Expression::expressionWithFormat($format);
    }

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->valueExpressionFormat) {
            $this->valueExpressionFormat = $this->valueExpressionFormat |> trim(...);
        }
    }
}
