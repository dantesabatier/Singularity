<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\SubclassFileWriter;
use App\Model\Entity;
use App\Model\EntityType;
use App\Tests\Support\GeneratorTestCase;
use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class SubclassFileWriterTest extends GeneratorTestCase
{
    private const string namespace = "Bookstore\\Model";

    /**
     * Returns the source the writer generates for an entity, without touching the disk.
     * @throws Exception
     */
    private function write(Entity $entity, ?URL $url = null): string
    {
        return new SubclassFileWriter($url ?? $this->temporaryURL($entity->name, "php"), $this->seal($entity), $entity->name, self::namespace, fn(Entity $destination, /** @noinspection PhpUnusedParameterInspection */ string $namespace): string => $destination->name)->contents;
    }

    /**
     * Puts a hand-written class on disk for the writer to read back and extend.
     * @throws Exception
     */
    private function existingFile(string $contents): URL
    {
        $url = $this->temporaryURL("Existing", "php");
        FileManager::default()->createFile($url->path, $contents, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        return $url;
    }

    /**
     * @throws Exception
     */
    public function testAFreshEntityBecomesAFinalClassExtendingManagedObject(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $contents = $this->write($book);
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n\nnamespace Bookstore\\Model;\n", $contents);
        self::assertStringContainsString("final class Book extends ManagedObject\n{\n}", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheAttributesAreDocumentedAsPropertiesOnTheGeneratedClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $this->addAttribute($book, "pages", AttributeType::integer32);
        $contents = $this->write($book);
        self::assertStringContainsString(" * @property string \$title", $contents);
        self::assertStringContainsString(" * @property int \$pages", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheImportsTheGeneratedCodeNeedsAreWrittenAboveTheClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "publishedOn", AttributeType::date);
        $contents = $this->write($book);
        self::assertStringContainsString("use Sabatier\\CoreData\\ManagedObject;", $contents);
        self::assertStringContainsString("use Sabatier\\Foundation\\Date;", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAbstractEntityBecomesAnAbstractClass(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $book->isAbstract = true;
        $this->addAttribute($book, "title");
        self::assertStringContainsString("abstract class Book extends ManagedObject", $this->write($book));
    }

    /**
     * @throws Exception
     */
    public function testASubentityExtendsItsSuperentityRatherThanManagedObject(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $manual = $this->makeEmptyEntity("Manual");
        $manual->superentity = $book;
        $this->addAttribute($manual, "revision");
        $contents = $this->write($manual);
        self::assertStringContainsString("class Manual extends Book", $contents);
        self::assertStringNotContainsString("extends ManagedObject", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAToManyRelationshipGetsTheCollectionMethodsTheFrameworkSynthesises(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $chapter = $this->makeEmptyEntity("Chapter");
        $relationship = $this->addRelationship($book, "chapters", "Chapter", true);
        $this->seal($book);
        self::assertSame($chapter, $relationship->destinationEntity);
        $contents = $this->write($book);
        foreach (["addChaptersObject(Chapter \$object)", "removeChaptersObject(Chapter \$object)", "addChapters(Set<Chapter> \$objects)", "removeChapters(Set<Chapter> \$objects)", "intersectChapters(Set<Chapter> \$objects)", "setChapters(Set<Chapter> \$objects)"] as $signature) {
            self::assertStringContainsString($signature, $contents);
        }
    }

    /**
     * @throws Exception
     */
    public function testAToOneRelationshipGetsNoCollectionMethodsBecauseItHoldsASingleObject(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->makeEmptyEntity("Author");
        $this->addRelationship($book, "author", "Author");
        self::assertStringNotContainsString("@method", $this->write($book));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipPointingAtNoEntityGetsNoCollectionMethods(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addRelationship($book, "chapters", "Chapter", true);
        self::assertStringNotContainsString("@method", $this->write($book));
    }

    /**
     * @throws Exception
     */
    public function testAGuardedAttributeIsWrittenAsARealPropertyInsideTheClassBody(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $contents = $this->write($book);
        self::assertStringContainsString("#[Readable([\"Admin\"], AuthorizationScope::all)]", $contents);
        self::assertStringContainsString("public string \$title {", $contents);
        self::assertStringContainsString("get => \$this->valueForKey(__PROPERTY__);", $contents);
        self::assertStringContainsString("final class Book extends ManagedObject\n{\n    #[Readable", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAccessControlOnTheEntityItselfIsWrittenAboveTheClassDeclaration(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $this->addAccessControl($book, "Readable");
        $contents = $this->write($book);
        self::assertMatchesRegularExpression("/#\\[Readable\\(\\[\"Admin\"\\], AuthorizationScope::all\\)\\]\\nfinal class Book/", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizableEntityImplementsAuthorizableAndCarriesItsContract(): void
    {
        $user = $this->makeRoleEntity("User", EntityType::authorizable);
        $contents = $this->write($user);
        self::assertStringContainsString("class User extends ManagedObject implements Authorizable", $contents);
        foreach (["public string \$username {", "public ?string \$password {", "public bool \$isEnabled {", "public int \$refreshTokenVersion {", "public Set \$roles {"] as $declaration) {
            self::assertStringContainsString($declaration, $contents);
        }
    }

    /**
     * @throws Exception
     */
    public function testEveryPropertyTheRoleContractDemandsIsMarkedAsAnOverride(): void
    {
        $contents = $this->write($this->makeRoleEntity("User", EntityType::authorizable));
        self::assertSame(6, substr_count($contents, "#[Override]"));
        self::assertStringContainsString("#[Override]\n    public string \$username {", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAccessControlOnARoleContractPropertyIsCarriedOntoTheGeneratedProperty(): void
    {
        $user = $this->makeRoleEntity("User", EntityType::authorizable);
        $this->addAccessControl($this->addAttribute($user, "username"), "Readable");
        $contents = $this->write($user);
        self::assertStringContainsString("#[Readable([\"Admin\"], AuthorizationScope::all)]", $contents);
        self::assertStringContainsString("public string \$username {", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizableEntityGetsTheDefaultRepresentationTheFrameworkReads(): void
    {
        $contents = $this->write($this->makeRoleEntity("User", EntityType::authorizable));
        self::assertStringContainsString("public static function defaultRepresentation(): Dictionary", $contents);
        self::assertStringContainsString("\"username\" => AttributeType::string,", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizableRoleEntityImplementsAuthorizableRoleAndCarriesItsContract(): void
    {
        $contents = $this->write($this->makeRoleEntity("UserRole", EntityType::authorizableRole));
        self::assertStringContainsString("class UserRole extends ManagedObject implements AuthorizableRole", $contents);
        self::assertStringContainsString("public string \$name {", $contents);
        self::assertStringContainsString("public Set \$authorizations {", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAnAuthorizationEntityImplementsAuthorizationAndCarriesItsContract(): void
    {
        $contents = $this->write($this->makeRoleEntity("Permission", EntityType::authorization));
        self::assertStringContainsString("class Permission extends ManagedObject implements Authorization", $contents);
        self::assertStringContainsString("public AuthorizationType \$type {", $contents);
        self::assertStringContainsString("public AuthorizationScope \$scope {", $contents);
    }

    /**
     * @throws Exception
     */
    public function testARolePropertyIsNotAlsoDocumentedAsADocBlockBecauseTheClassDeclaresIt(): void
    {
        $contents = $this->write($this->makeRoleEntity("User", EntityType::authorizable));
        self::assertStringNotContainsString(" * @property string \$username", $contents);
    }

    /**
     * @throws Exception
     */
    public function testARolePropertyTheDeveloperAlreadyDeclaredIsNotGeneratedASecondTime(): void
    {
        $user = $this->makeRoleEntity("User", EntityType::authorizable);
        $url = $this->existingFile("<?php\n\nnamespace Bookstore\\Model;\n\nclass User extends ManagedObject implements Authorizable\n{\n    public string \$username = \"\";\n}\n");
        self::assertSame(1, substr_count($this->write($user, $url), "\$username"));
    }

    /**
     * @throws Exception
     */
    public function testTheDefaultRepresentationTheDeveloperAlreadyWroteIsNotGeneratedASecondTime(): void
    {
        $user = $this->makeRoleEntity("User", EntityType::authorizable);
        $url = $this->existingFile("<?php\n\nnamespace Bookstore\\Model;\n\nclass User extends ManagedObject implements Authorizable\n{\n    public static function defaultRepresentation(): Dictionary\n    {\n        return new Dictionary();\n    }\n}\n");
        self::assertSame(1, substr_count($this->write($user, $url), "function defaultRepresentation"));
    }

    /**
     * @throws Exception
     */
    public function testTheFileHeaderIsRewrittenRatherThanCarriedIntoTheClassBody(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $url = $this->existingFile("<?php\n\ndeclare(strict_types=1);\n\nnamespace Bookstore\\Model;\n\nclass Book extends ManagedObject\n{\n}\n");
        self::assertSame(1, substr_count($this->write($book, $url), "declare(strict_types=1);"));
    }

    /**
     * @throws Exception
     */
    public function testTheImportsTheDeveloperAlreadyWroteSurviveRegeneration(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $url = $this->existingFile("<?php\n\nnamespace Bookstore\\Model;\n\nuse Bookstore\\Support\\Slug;\n\nclass Book extends ManagedObject\n{\n}\n");
        self::assertStringContainsString("use Bookstore\\Support\\Slug;", $this->write($book, $url));
    }

    /**
     * @throws Exception
     */
    public function testTheDocBlocksTheDeveloperAlreadyWroteSurviveRegeneration(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $url = $this->existingFile("<?php\n\nnamespace Bookstore\\Model;\n\n/**\n * @property string \$handWritten\n */\nclass Book extends ManagedObject\n{\n}\n");
        self::assertStringContainsString(" * @property string \$handWritten", $this->write($book, $url));
    }

    /**
     * @throws Exception
     */
    public function testTheBodyTheDeveloperAlreadyWroteSurvivesRegeneration(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAccessControl($this->addAttribute($book, "title"), "Readable");
        $url = $this->existingFile("<?php\n\nnamespace Bookstore\\Model;\n\nclass Book extends ManagedObject\n{\n    public function summary(): string\n    {\n        return \"\";\n    }\n}\n");
        $contents = $this->write($book, $url);
        self::assertStringContainsString("public function summary(): string", $contents);
        self::assertStringContainsString("public string \$title {", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAFileWithoutAClassAtAllIsRegeneratedFromScratch(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $url = $this->existingFile("<?php\n\n// nothing here yet\n");
        self::assertStringContainsString("final class Book extends ManagedObject", $this->write($book, $url));
    }

    /**
     * @throws Exception
     */
    public function testAnEntityWithNoPropertiesAtAllStillProducesACompilableClass(): void
    {
        $contents = $this->write($this->makeEmptyEntity("Book"));
        self::assertStringContainsString("final class Book extends ManagedObject\n{\n}", $contents);
        self::assertStringNotContainsString("/**\n */", $contents);
    }

    /**
     * @throws Exception
     */
    public function testSavingPutsTheGeneratedSourceOnDiskAtTheGivenLocation(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->addAttribute($book, "title");
        $url = $this->temporaryURL("Book", "php");
        new SubclassFileWriter($url, $this->seal($book), "Book", self::namespace, fn(Entity $destination, /** @noinspection PhpUnusedParameterInspection */ string $namespace): string => $destination->name)->save();
        self::assertTrue(FileManager::default()->fileExists($url->path));
        self::assertStringContainsString("final class Book extends ManagedObject", (string)FileManager::default()->contents($url->path));
    }
}
