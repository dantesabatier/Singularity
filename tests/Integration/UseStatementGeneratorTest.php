<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\Generators\UseStatementGenerator;
use App\Model\Entity;
use App\Model\EntityType;
use App\Tests\Support\GeneratorTestCase;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\Set;

final class UseStatementGeneratorTest extends GeneratorTestCase
{
    private UseStatementGenerator $generator;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new UseStatementGenerator();
    }

    /**
     * @return Set<string>
     * @throws Exception
     */
    private function generate(Entity $entity): Set
    {
        return $this->generator->generate($this->seal($entity), $this->emptyStringSet());
    }

    /**
     * @throws Exception
     */
    public function testAnEntityWithoutASuperentityImportsTheManagedObjectItExtends(): void
    {
        $uses = $this->generate($this->makeEmptyEntity("Book"));
        self::assertTrue($uses->containsElement("use Sabatier\\CoreData\\ManagedObject;"));
    }

    /**
     * @throws Exception
     */
    public function testAnEntityWithASuperentityImportsNoManagedObjectBecauseItExtendsTheSuperentity(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $manual = $this->makeEmptyEntity("Manual");
        $manual->superentity = $book;
        $uses = $this->generate($manual);
        self::assertFalse($uses->containsElement("use Sabatier\\CoreData\\ManagedObject;"));
    }

    /**
     * @throws Exception
     */
    public function testADateAttributeImportsTheDateClassItIsTypedAs(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "publishedOn", AttributeType::date);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\Date;"));
    }

    /**
     * @throws Exception
     */
    public function testAUuidAttributeImportsTheUuidClassItIsTypedAs(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "identifier", AttributeType::uuid);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\UUID;"));
    }

    /**
     * @throws Exception
     */
    public function testAUriAttributeImportsTheUrlClassItIsTypedAs(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "homepage", AttributeType::uri);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\URL;"));
    }

    /**
     * @throws Exception
     */
    public function testAnObjectIdAttributeImportsTheManagedObjectIdClassItIsTypedAs(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "reference", AttributeType::objectID);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\CoreData\\ManagedObjectID;"));
    }

    /**
     * @throws Exception
     */
    public function testACompositeAttributeImportsTheDictionaryItIsTypedAs(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "metadata", AttributeType::compositeAttributeType);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\Dictionary;"));
    }

    /**
     * @throws Exception
     */
    public function testAStringAttributeImportsNothingBecauseItsTypeIsNotAClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $uses = $this->generate($book);
        self::assertSame(["use Sabatier\\CoreData\\ManagedObject;"], $uses->array);
    }

    /**
     * @throws Exception
     */
    public function testAToManyRelationshipImportsTheSetThatHoldsIt(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "chapters", "Chapter", true);
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\Set;"));
    }

    /**
     * @throws Exception
     */
    public function testAToOneRelationshipImportsNoSetBecauseItHoldsASingleObject(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "author", "Author");
        self::assertFalse($this->generate($book)->containsElement("use Sabatier\\Foundation\\Set;"));
    }

    /**
     * @throws Exception
     */
    public function testAFetchedPropertyImportsTheArrayClassThatHoldsIt(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addFetchedProperty($book, "recentReviews", "Review");
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\ArrayClass;"));
    }

    /**
     * @throws Exception
     */
    public function testAnEntityWithoutFetchedPropertiesImportsNoArrayClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        self::assertFalse($this->generate($book)->containsElement("use Sabatier\\Foundation\\ArrayClass;"));
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizableEntityImportsEverythingItsGeneratedContractNeeds(): void
    {
        $uses = $this->generate($this->makeRoleEntity("User", EntityType::authorizable));
        foreach (["use Sabatier\\Service\\Authorizable;", "use Sabatier\\CoreData\\AttributeType;", "use Sabatier\\Foundation\\Dictionary;", "use Sabatier\\Foundation\\Set;", "use Override;"] as $statement) {
            self::assertTrue($uses->containsElement($statement), "Missing $statement");
        }
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizableRoleEntityImportsEverythingItsGeneratedContractNeeds(): void
    {
        $uses = $this->generate($this->makeRoleEntity("UserRole", EntityType::authorizableRole));
        foreach (["use Sabatier\\Service\\AuthorizableRole;", "use Sabatier\\Service\\Authorization;", "use Sabatier\\Foundation\\Set;", "use Override;"] as $statement) {
            self::assertTrue($uses->containsElement($statement), "Missing $statement");
        }
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizationEntityImportsEverythingItsGeneratedContractNeeds(): void
    {
        $uses = $this->generate($this->makeRoleEntity("Permission", EntityType::authorization));
        foreach (["use Sabatier\\Service\\Authorization;", "use Sabatier\\Service\\AuthorizationType;", "use Sabatier\\Service\\AuthorizationScope;", "use Override;"] as $statement) {
            self::assertTrue($uses->containsElement($statement), "Missing $statement");
        }
    }

    /**
     * @throws Exception
     */
    public function testAnOrdinaryEntityImportsNoneOfTheAuthorizationContract(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $uses = $this->generate($book);
        self::assertFalse($uses->containsElement("use Sabatier\\Service\\Authorizable;"));
        self::assertFalse($uses->containsElement("use Override;"));
    }

    /**
     * @throws Exception
     */
    public function testTheStatementsAlreadyInTheFileAreKeptAlongsideTheGeneratedOnes(): void
    {
        $book = $this->makeEmptyEntity("Book");
        /** @var Set<string> $existing */
        $existing = new Set(["use App\\Support\\Custom;"]);
        $uses = $this->generator->generate($this->seal($book), $existing);
        self::assertTrue($uses->containsElement("use App\\Support\\Custom;"));
        self::assertTrue($uses->containsElement("use Sabatier\\CoreData\\ManagedObject;"));
    }

    /**
     * @throws Exception
     */
    public function testTheStatementsComeOutSortedSoTheGeneratedFileIsStable(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "publishedOn", AttributeType::date);
        $this->addRelationship($book, "chapters", "Chapter", true);
        $statements = $this->generate($book)->array;
        $sorted = $statements;
        sort($sorted);
        self::assertSame($sorted, $statements);
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeNamingAClassThatExistsImportsThatClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $attribute = $this->addAttribute($book, "payload", AttributeType::transformable);
        $attribute->attributeValueClassName = Set::class;
        self::assertTrue($this->generate($book)->containsElement("use Sabatier\\Foundation\\Set;"));
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeNamingAClassThatDoesNotExistImportsNothing(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $attribute = $this->addAttribute($book, "payload", AttributeType::transformable);
        $attribute->attributeValueClassName = "App\\Nowhere\\MissingClass";
        $uses = $this->generate($book);
        self::assertFalse($uses->containsElement("use App\\Nowhere\\MissingClass;"));
    }
}
