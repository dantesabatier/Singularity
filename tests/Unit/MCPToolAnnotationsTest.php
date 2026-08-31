<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\MCPTools\DesignModelTool;
use App\MCPTools\GenerateSubclassesTool;
use App\MCPTools\SaveProjectTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class MCPToolAnnotationsTest extends TestCase
{
    /**
     * @param class-string<AbstractTool> $class The custom tool whose catalogue is inspected without executing it.
     * @param bool $readOnly Whether every operation is read-only.
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider("customTools")]
    public function customToolsPublishConservativeEffectsWithinTheApplication(string $class, bool $readOnly): void
    {
        $tool = new ReflectionClass($class)->newInstanceWithoutConstructor();
        $descriptor = new ToolRegistry(new ArrayClass([$tool]))->list->first;
        $this->assertNotNull($descriptor);

        $this->assertSame([
            "readOnlyHint" => $readOnly,
            "destructiveHint" => true,
            "idempotentHint" => false,
            "openWorldHint" => false,
        ], $descriptor->jsonSerialize()["annotations"]);
    }

    /** @return array<string, array{class-string<AbstractTool>, bool}> */
    public static function customTools(): array
    {
        return [
            "DesignModel" => [DesignModelTool::class, true],
            "GenerateSubclasses" => [GenerateSubclassesTool::class, false],
            "SaveProject" => [SaveProjectTool::class, false],
        ];
    }
}
