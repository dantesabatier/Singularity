<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\Generators\PropertyAttributeGenerator;
use App\FileWriters\Generators\PropertyDocBlockGenerator;
use App\Model\Entity;
use App\Tests\Support\GeneratorTestCase;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;

final class PropertyDocBlockGeneratorTest extends GeneratorTestCase
{
    private PropertyDocBlockGenerator $generator;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PropertyDocBlockGenerator(new PropertyAttributeGenerator());
    }

    /**
     * @param array<string> $reservedPropertyNames
     * @return list<string>
     * @throws Exception
     */
    private function generate(Entity $entity, array $reservedPropertyNames = [], string $declaration = "class Book extends ManagedObject\n{\n}\n"): array
    {
        return $this->render($this->generator->generate($this->seal($entity), $this->emptyStringSet(), $reservedPropertyNames, $declaration));
    }

    /**
     * The generator yields value objects beside the doc blocks already in the file, and only their
     * rendered form ever reaches the generated class.
     * @param Set<string> $properties
     * @return list<string>
     */
    private function render(Set $properties): array
    {
        return $properties->map(fn(object|string $property): string => $property instanceof ObjectClass ? $property->description : (string)$property)->array;
    }

    /**
     * @throws Exception
     */
    public function testAStringAttributeIsDocumentedAsAStringProperty(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        self::assertSame([" * @property string \$title"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnOptionalAttributeIsDocumentedAsNullable(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "subtitle")->isOptional = true;
        self::assertSame([" * @property string|null \$subtitle"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testADerivedAttributeIsDocumentedAsReadOnlyBecauseTheStoreComputesIt(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "slug")->isDerived = true;
        self::assertSame([" * @property-read string \$slug"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testEveryScalarAttributeTypeIsDocumentedWithItsPhpType(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "pages", AttributeType::integer32);
        $this->addAttribute($book, "price", AttributeType::decimal);
        $this->addAttribute($book, "weight", AttributeType::float);
        $this->addAttribute($book, "isLent", AttributeType::boolean);
        $this->addAttribute($book, "cover", AttributeType::binaryData);
        $properties = $this->generate($book);
        self::assertContains(" * @property int \$pages", $properties);
        self::assertContains(" * @property double \$price", $properties);
        self::assertContains(" * @property float \$weight", $properties);
        self::assertContains(" * @property bool \$isLent", $properties);
        self::assertContains(" * @property string \$cover", $properties);
    }

    /**
     * @throws Exception
     */
    public function testATransformableAttributeIsDocumentedAsMixed(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "payload", AttributeType::transformable);
        self::assertSame([" * @property mixed \$payload"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnOptionalMixedAttributeIsNotMarkedNullableBecauseMixedAlreadyAdmitsNull(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "payload", AttributeType::transformable)->isOptional = true;
        self::assertSame([" * @property mixed \$payload"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAClassBackedAttributeIsDocumentedWithTheUnqualifiedClassName(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "publishedOn", AttributeType::date);
        self::assertSame([" * @property Date \$publishedOn"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testACompositeAttributeIsDocumentedAsADictionaryOfMixedValues(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "metadata", AttributeType::compositeAttributeType);
        self::assertSame([" * @property Dictionary<mixed> \$metadata"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testABoundedIntegerIsDocumentedWithTheRangeItAccepts(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $pages = $this->addAttribute($book, "pages", AttributeType::integer32);
        $pages->minValue = 1;
        $pages->maxValue = 5000;
        self::assertSame([" * @property int<1, 5000> \$pages"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnIntegerBoundedOnOneSideOnlyLeavesTheOtherSideOpen(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "pages", AttributeType::integer32)->minValue = 1;
        self::assertSame([" * @property int<1, max> \$pages"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnUnboundedIntegerIsDocumentedWithoutARange(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "pages", AttributeType::integer32);
        self::assertSame([" * @property int \$pages"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testABoundedNonIntegerIsDocumentedWithoutARangeBecauseOnlyIntegersCarryOne(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $price = $this->addAttribute($book, "price", AttributeType::decimal);
        $price->minValue = 1;
        $price->maxValue = 100;
        self::assertSame([" * @property double \$price"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeWithNoUsableTypeIsNotDocumentedAtAll(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "mystery", AttributeType::undefined);
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAToOneRelationshipIsDocumentedWithItsDestinationEntity(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "author", "Author");
        self::assertSame([" * @property Author \$author"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAToManyRelationshipIsDocumentedAsASetOfTheDestinationEntity(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "chapters", "Chapter", true);
        self::assertSame([" * @property Set<Chapter> \$chapters"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnOptionalRelationshipIsDocumentedAsNullable(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "author", "Author")->isOptional = true;
        self::assertSame([" * @property Author|null \$author"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyIsDocumentedAsAReadOnlyArrayOfTheEntityItFetches(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addFetchedProperty($book, "recentReviews", "Review");
        self::assertSame([" * @property-read ArrayClass<Review> \$recentReviews"], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAReservedAttributeNameIsLeftToTheRoleGeneratorThatOwnsIt(): void
    {
        $user = $this->makeEmptyEntity("User");
        $this->addAttribute($user, "username");
        $this->addAttribute($user, "nickname");
        self::assertSame([" * @property string \$nickname"], $this->generate($user, ["username"]));
    }

    /**
     * @throws Exception
     */
    public function testAReservedRelationshipNameIsLeftToTheRoleGeneratorThatOwnsIt(): void
    {
        $user = $this->makeEmptyEntity("User");
        $this->addRelationship($user, "roles", "UserRole", true);
        self::assertSame([], $this->generate($user, ["roles"]));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeTheDeveloperAlreadyDeclaredByHandIsNotDocumentedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        self::assertSame([], $this->generate($book, [], "class Book extends ManagedObject\n{\n    public string \$title;\n}\n"));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipTheDeveloperAlreadyDeclaredByHandIsNotDocumentedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "author", "Author");
        self::assertSame([], $this->generate($book, [], "class Book extends ManagedObject\n{\n    public Author \$author;\n}\n"));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyTheDeveloperAlreadyDeclaredByHandIsNotDocumentedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addFetchedProperty($book, "recentReviews", "Review");
        self::assertSame([], $this->generate($book, [], "class Book extends ManagedObject\n{\n    public ArrayClass \$recentReviews;\n}\n"));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeCarryingAccessControlBecomesARealPropertyRatherThanADocBlock(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipCarryingAccessControlBecomesARealPropertyRatherThanADocBlock(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addRelationship($book, "author", "Author"), "Writable");
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyCarryingAccessControlBecomesARealPropertyRatherThanADocBlock(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $fetchedProperty = $this->addFetchedProperty($book, "recentReviews", "Review");
        $accessControl = $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $accessControl->property = $fetchedProperty;
        self::assertNotContains(" * @property-read ArrayClass<Review> \$recentReviews", $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testTheDocBlocksAlreadyInTheFileAreKeptAlongsideTheGeneratedOnes(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        /** @var Set<string> $existing */
        $existing = new Set([" * @property string \$handWritten"]);
        $properties = $this->render($this->generator->generate($this->seal($book), $existing, [], "class Book\n{\n}\n"));
        self::assertContains(" * @property string \$handWritten", $properties);
        self::assertContains(" * @property string \$title", $properties);
    }



}
