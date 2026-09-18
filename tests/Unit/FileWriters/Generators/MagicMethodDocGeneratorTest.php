<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\Generators;

use App\FileWriters\Generators\MagicMethodDocGenerator;
use App\Model\Entity;
use App\Tests\Support\GeneratorTestCase;
use Exception;

final class MagicMethodDocGeneratorTest extends GeneratorTestCase
{
    private function generator(): MagicMethodDocGenerator
    {
        return new MagicMethodDocGenerator(fn(Entity $entity, string $namespace): string => "$namespace\\$entity->name");
    }

    /**
     * @throws Exception
     */
    public function testAToManyRelationshipYieldsTheSixCollectionAccessors(): void
    {
        $author = $this->makeEmptyEntity("Author");
        $this->makeEmptyEntity("Book");
        $this->addRelationship($author, "books", "Book", isToMany: true);
        $this->seal($author);

        $blocks = $this->generator()->generate($author, "App\\Model");
        self::assertSame(1, $blocks->count, "one block per to-many relationship");

        $block = $blocks->first;
        self::assertStringContainsString(" * @method void addBooksObject(App\\Model\\Book \$object)", $block);
        self::assertStringContainsString(" * @method void removeBooksObject(App\\Model\\Book \$object)", $block);
        self::assertStringContainsString(" * @method void addBooks(Set<App\\Model\\Book> \$objects)", $block);
        self::assertStringContainsString(" * @method void removeBooks(Set<App\\Model\\Book> \$objects)", $block);
        self::assertStringContainsString(" * @method Set<App\\Model\\Book> intersectBooks(Set<App\\Model\\Book> \$objects)", $block);
        self::assertStringContainsString(" * @method void setBooks(Set<App\\Model\\Book> \$objects)", $block);
    }

    /**
     * @throws Exception
     */
    public function testAToOneRelationshipProducesNoMagicMethods(): void
    {
        $book = $this->makeEmptyEntity("Book");
        $this->makeEmptyEntity("Author");
        $this->addRelationship($book, "author", "Author");
        $this->seal($book);

        self::assertTrue($this->generator()->generate($book, "App\\Model")->isEmpty);
    }

    /**
     * @throws Exception
     */
    public function testARelationshipToAnUnknownEntityIsSkipped(): void
    {
        $author = $this->makeEmptyEntity("Author");
        $this->addRelationship($author, "ghosts", "Ghost", isToMany: true);
        $this->seal($author);

        self::assertTrue($this->generator()->generate($author, "App\\Model")->isEmpty, "no destination entity in the model means no methods");
    }
}
