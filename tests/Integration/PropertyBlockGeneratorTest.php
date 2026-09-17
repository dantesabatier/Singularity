<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\Generators\PropertyAttributeGenerator;
use App\FileWriters\Generators\PropertyBlockGenerator;
use App\Model\Entity;
use App\Tests\Support\GeneratorTestCase;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;

final class PropertyBlockGeneratorTest extends GeneratorTestCase
{
    private PropertyBlockGenerator $generator;
    /** @var Set<string> */
    private Set $uses;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PropertyBlockGenerator(new PropertyAttributeGenerator());
        $this->uses = $this->emptyStringSet();
    }

    /**
     * @param array<string> $reservedPropertyNames
     * @return list<string>
     * @throws Exception
     */
    private function generate(Entity $entity, string $declaration = "class Book extends ManagedObject\n{\n}\n", array $reservedPropertyNames = []): array
    {
        return $this->generator->generate($this->seal($entity), $this->uses, $declaration, $reservedPropertyNames)->map(fn(object $block): string => (string)$block)->array;
    }

    /**
     * @param list<string> $blocks
     */
    private function declares(array $blocks, string $declaration): bool
    {
        return new ArrayClass($blocks)->contains(fn(string $block): bool => str_contains($block, $declaration));
    }

    /**
     * @throws Exception
     */
    public function testAPropertyWithoutAccessControlStaysADocBlockAndGetsNoRealProperty(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipThatIsNeitherOwnedNorGuardedStaysADocBlock(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "author", "Author");
        $this->addRelationship($book, "chapters", "Chapter", true);
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyWithoutAccessControlStaysADocBlock(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addFetchedProperty($book, "recentReviews", "Review");
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeUnderAccessControlBecomesARealPropertyBackedByKeyValueCoding(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $blocks = $this->generate($book);
        self::assertCount(1, $blocks);
        self::assertStringContainsString("public string \$title {", $blocks[0]);
        self::assertStringContainsString("get => \$this->valueForKey(__PROPERTY__);", $blocks[0]);
        self::assertStringContainsString("\$this->setValueForKey(\$value, __PROPERTY__);", $blocks[0]);
    }

    /**
     * @throws Exception
     */
    public function testTheAccessControlIsRenderedAsThePhpAttributeThatGuardsTheProperty(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable", ["Admin"]);
        self::assertStringContainsString("#[Readable([\"Admin\"], AuthorizationScope::all)]", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAnOwnScopedAccessControlNamesThatScopeInThePhpAttribute(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Writable", ["Admin"], AuthorizationScope::own);
        self::assertStringContainsString("AuthorizationScope::own", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAPredicateOnTheAccessControlIsCarriedIntoThePhpAttribute(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable", ["Admin"], AuthorizationScope::all, "isPublished == 1");
        self::assertStringContainsString("where: \"isPublished == 1\"", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAnAccessControlThatIsSwitchedOffProducesNoPropertyAtAll(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable")->isEnabled = false;
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testADisabledAccessControlIsLeftOutOfAPropertyThatAnotherControlStillGuards(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $title = $this->addAttribute($book, "title");
        $this->addAccessControl($title, "Readable");
        $this->addAccessControl($title, "Writable")->isEnabled = false;
        $block = $this->generate($book)[0];
        self::assertStringContainsString("#[Readable(", $block);
        self::assertStringNotContainsString("#[Writable(", $block);
    }

    /**
     * @throws Exception
     */
    public function testAnOptionalAttributeUnderAccessControlIsDeclaredNullable(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $subtitle = $this->addAttribute($book, "subtitle");
        $subtitle->isOptional = true;
        $this->addAccessControl($subtitle, "Readable");
        self::assertStringContainsString("public ?string \$subtitle {", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAMixedAttributeIsNotDeclaredNullableBecauseMixedAlreadyAdmitsNull(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $payload = $this->addAttribute($book, "payload", AttributeType::transformable);
        $payload->isOptional = true;
        $this->addAccessControl($payload, "Readable");
        self::assertStringContainsString("public mixed \$payload {", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAClassBackedAttributeIsDeclaredWithTheUnqualifiedClassName(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "publishedOn", AttributeType::date), "Readable");
        self::assertStringContainsString("public Date \$publishedOn {", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeWithNoUsableTypeProducesNoPropertyEvenUnderAccessControl(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "mystery", AttributeType::undefined), "Readable");
        self::assertSame([], $this->generate($book));
    }

    /**
     * @throws Exception
     */
    public function testAToManyRelationshipUnderAccessControlIsDeclaredAsASet(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addRelationship($book, "chapters", "Chapter", true), "Readable");
        self::assertStringContainsString("public Set \$chapters {", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAToOneRelationshipUnderAccessControlIsDeclaredAsItsDestinationEntity(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addRelationship($book, "author", "Author"), "Readable");
        self::assertStringContainsString("public Author \$author {", $this->generate($book)[0]);
    }

    /**
     * @throws Exception
     */
    public function testAnOwnerRelationshipBecomesARealPropertyWithoutNeedingAnyAccessControl(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "owner", "User")->isOwner = true;
        $blocks = $this->generate($book);
        self::assertCount(1, $blocks);
        self::assertStringContainsString("#[Owner]", $blocks[0]);
        self::assertStringContainsString("public User \$owner {", $blocks[0]);
    }

    /**
     * @throws Exception
     */
    public function testAnOwnerRelationshipImportsTheOwnerAttributeItIsMarkedWith(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "owner", "User")->isOwner = true;
        $this->generate($book);
        self::assertTrue($this->uses->containsElement("use Sabatier\\Service\\Owner;"));
    }

    /**
     * @throws Exception
     */
    public function testAGuardedPropertyImportsTheAttributeAndTheScopeItNames(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $this->generate($book);
        self::assertTrue($this->uses->containsElement("use Sabatier\\Service\\Readable;"));
        self::assertTrue($this->uses->containsElement("use Sabatier\\Service\\AuthorizationScope;"));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyUnderAccessControlIsDeclaredAsAnArrayClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $fetchedProperty = $this->addFetchedProperty($book, "recentReviews", "Review");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable")->property = $fetchedProperty;
        self::assertTrue($this->declares($this->generate($book), "public ArrayClass \$recentReviews {"));
    }

    /**
     * @throws Exception
     */
    public function testAReservedAttributeNameIsLeftToTheRoleGeneratorThatOwnsIt(): void
    {
        $user = $this->makeEmptyEntity("User");
        $this->addAccessControl($this->addAttribute($user, "username"), "Readable");
        self::assertSame([], $this->generate($user, "class User extends ManagedObject\n{\n}\n", ["username"]));
    }

    /**
     * @throws Exception
     */
    public function testAReservedRelationshipNameIsLeftToTheRoleGeneratorThatOwnsIt(): void
    {
        $user = $this->makeEmptyEntity("User");
        $this->addRelationship($user, "roles", "UserRole", true)->isOwner = true;
        self::assertSame([], $this->generate($user, "class User extends ManagedObject\n{\n}\n", ["roles"]));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeTheDeveloperAlreadyDeclaredByHandIsNotGeneratedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        self::assertSame([], $this->generate($book, "class Book extends ManagedObject\n{\n    public string \$title;\n}\n"));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipTheDeveloperAlreadyDeclaredByHandIsNotGeneratedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "owner", "User")->isOwner = true;
        self::assertSame([], $this->generate($book, "class Book extends ManagedObject\n{\n    public User \$owner;\n}\n"));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyTheDeveloperAlreadyDeclaredByHandIsNotGeneratedAgain(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $fetchedProperty = $this->addFetchedProperty($book, "recentReviews", "Review");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable")->property = $fetchedProperty;
        self::assertFalse($this->declares($this->generate($book, "class Book extends ManagedObject\n{\n    public ArrayClass \$recentReviews;\n}\n"), "\$recentReviews {"));
    }

    /**
     * @throws Exception
     */
    public function testTheBlocksComeOutAttributesFirstThenRelationships(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $this->addRelationship($book, "owner", "User")->isOwner = true;
        $blocks = $this->generate($book);
        self::assertCount(2, $blocks);
        self::assertStringContainsString("\$title {", $blocks[0]);
        self::assertStringContainsString("\$owner {", $blocks[1]);
    }
}
