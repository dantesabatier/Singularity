<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\LatteRenderer;
use App\Model\AccessControl;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchIndex;
use App\Model\FetchRequestTemplate;
use App\Model\Model;
use App\Model\Project;
use App\Model\Relationship;
use App\Model\Role;
use App\Model\UniquenessConstraint;
use App\Tests\Support\GeneratorTestCase;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;

final class LatteRendererTest extends GeneratorTestCase
{
    private LatteRenderer $renderer;
    private URL $viewsURL;

    /**
     * Stands up one bundle-shaped directory the renderer loads its templates from, so no probe
     * template is ever written into the application's own views.
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $bundleURL = $this->makeTemporaryDirectory("renderer");
        $this->viewsURL = $bundleURL->appendingPathComponent("Resources")->appendingPathComponent("Views");
        FileManager::default()->createDirectory($this->viewsURL, true);
        PropertyListSerialization::writePropertyList(new Dictionary(["CFBundleName" => "Bookstore"]), $bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $this->renderer = new LatteRenderer(Bundle::bundleWithURL($bundleURL));
    }

    /**
     * Renders a one-off template through the renderer under test.
     * @param array<string, mixed> $context
     * @throws Exception
     */
    private function render(string $template, array $context = []): string
    {
        $name = "Probe" . bin2hex(random_bytes(4));
        FileManager::default()->createFile($this->viewsURL->appendingPathComponent($name)->appendingPathExtension("latte")->path, $template);
        return $this->renderer->render($name, $context);
    }

    /**
     * @throws Exception
     */
    private function glyph(ManagedObject $object): string
    {
        return $this->render("{img(\$object)}", ["object" => $object]);
    }

    /**
     * @throws Exception
     */
    public function testATemplateIsRenderedWithTheContextItIsGiven(): void
    {
        self::assertSame("Bookstore", $this->render("{\$name}", ["name" => "Bookstore"]));
    }

    /**
     * @throws Exception
     */
    public function testTheReadableFilterSpellsAValueOutForAReader(): void
    {
        self::assertSame("true", $this->render("{\$value|readable}", ["value" => true]));
        self::assertSame("Bookstore", $this->render("{\$value|readable}", ["value" => "Bookstore"]));
    }

    /**
     * @throws Exception
     */
    public function testTheCamelCaseFilterTurnsAnEditedNameIntoAnIdentifier(): void
    {
        self::assertSame("publishedOn", $this->render("{\$value|camelCase}", ["value" => "published on"]));
    }

    /**
     * @throws Exception
     */
    public function testTheFirstLowerFilterLowersOnlyTheLeadingLetter(): void
    {
        self::assertSame("bookStore", $this->render("{\$value|firstLower}", ["value" => "BookStore"]));
    }

    /**
     * @throws Exception
     */
    public function testTheCoercedFilterReadsAValueBackAsTheAttributeTypeItIsStoredAs(): void
    {
        self::assertSame("42", $this->render("{\$value|coerced: \$type}", ["value" => "42", "type" => AttributeType::integer32->value]));
    }

    /**
     * @throws Exception
     */
    public function testTheNonemptyFilterTurnsABlankStringIntoNothingSoADefaultCanTakeOver(): void
    {
        self::assertSame("none", $this->render("{(\$value|nonempty) ?? \"none\"}", ["value" => ""]));
        self::assertSame("set", $this->render("{(\$value|nonempty) ?? \"none\"}", ["value" => "set"]));
    }

    /**
     * @throws Exception
     */
    public function testTheJsonFilterHandsTheFrontendAValueItCanParse(): void
    {
        self::assertSame("[1,2]", $this->render("{\$value|json|noescape}", ["value" => [1, 2]]));
    }

    /**
     * @throws Exception
     */
    public function testTheLocalizedStringFunctionIsAvailableToTemplates(): void
    {
        self::assertSame("Entity", $this->render("{localized_string(\"Entity\")}"));
    }

    /**
     * @throws Exception
     */
    public function testTheViteAssetFunctionHandsTemplatesTheEntryPointsToLoad(): void
    {
        self::assertSame("", $this->render("{vite_asset()->client}"));
    }

    /**
     * @throws Exception
     */
    public function testAnEntityIsDrawnWithItsOwnGlyph(): void
    {
        self::assertSame("E", $this->glyph($this->makeEmptyEntity("Book")));
    }

    /**
     * @throws Exception
     */
    public function testEachModelObjectThatCarriesItsClassNameIsDrawnWithIt(): void
    {
        $project = $this->makeProject("Library");
        self::assertSame("Project", $this->glyph($project));
        self::assertSame("Model", $this->glyph($project->model ?? self::fail("Project has no model")));
        self::assertSame("Role", $this->glyph($this->makeRole()));
        self::assertSame("AccessControl", $this->glyph($this->makeAccessControl()));
    }

    /**
     * @throws Exception
     */
    public function testANumericAttributeIsDrawnAsASingleNumberGlyphWhateverItsWidth(): void
    {
        $book = $this->makeEmptyEntity("Book");
        foreach ([AttributeType::integer16, AttributeType::integer32, AttributeType::integer64, AttributeType::decimal, AttributeType::double, AttributeType::float] as $type) {
            self::assertSame("N", $this->glyph($this->addAttribute($book, "value" . $type->value, $type)));
        }
    }

    /**
     * @throws Exception
     */
    public function testAUuidAttributeIsDrawnWithItsFullTypeNameRatherThanAnInitial(): void
    {
        self::assertSame("uuid", $this->glyph($this->addAttribute($this->makeEmptyEntity("Book"), "identifier", AttributeType::uuid)));
    }

    /**
     * @throws Exception
     */
    public function testAnOrdinaryAttributeIsDrawnWithTheInitialOfItsType(): void
    {
        $book = $this->makeEmptyEntity("Book");
        self::assertSame("S", $this->glyph($this->addAttribute($book, "title")));
        self::assertSame("B", $this->glyph($this->addAttribute($book, "isLent", AttributeType::boolean)));
        self::assertSame("D", $this->glyph($this->addAttribute($book, "publishedOn", AttributeType::date)));
    }

    /**
     * @throws Exception
     */
    public function testARelationshipIsDrawnByHowManyObjectsItHolds(): void
    {
        $book = $this->makeEmptyEntity("Book");
        self::assertSame("O", $this->glyph($this->addRelationship($book, "author", "Author")));
        self::assertSame("M", $this->glyph($this->addRelationship($book, "chapters", "Chapter", true)));
    }

    /**
     * @throws Exception
     */
    public function testTheFetchingObjectsAreDrawnWithTheSameGlyph(): void
    {
        self::assertSame("F", $this->glyph($this->addFetchedProperty($this->makeEmptyEntity("Book"), "recentReviews", "Review")));
        self::assertSame("F", $this->glyph(new FetchRequestTemplate($this->context)));
    }

    /**
     * @throws Exception
     */
    public function testAnIndexAndAConstraintAreDrawnWithTheirOwnInitials(): void
    {
        self::assertSame("I", $this->glyph(new FetchIndex($this->context)));
        self::assertSame("U", $this->glyph(new UniquenessConstraint($this->context)));
    }



    /**
     * @throws Exception
     */
    private function makeRole(): Role
    {
        $role = new Role($this->context);
        $role->name = "Admin";
        $this->model->addRolesObject($role);
        return $role;
    }

    /**
     * @throws Exception
     */
    private function makeAccessControl(): AccessControl
    {
        $accessControl = new AccessControl($this->context);
        $accessControl->name = "Readable";
        return $accessControl;
    }
}
