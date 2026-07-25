<?php

declare(strict_types=1);

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\LocalizationExtractor;
use function Sabatier\Foundation\fatal_error;

/**
 * Extracts the `localized_string()` calls of a generated project into its own
 * gettext catalogs. Wraps the Foundation `LocalizationExtractor` around the
 * project's bundle, so Singularity provides this capability to every project
 * it authors without each project shipping an extraction script of its own.
 */
final readonly class ExtractLocalizablesTransaction implements Transaction
{
    /**
     * @param Project $project The project whose source tree is scanned.
     * @param ArrayClass<string> $languages The language codes to extract.
     */
    public function __construct(private Project $project, private ArrayClass $languages)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $url = $this->project->url ?? fatal_error("Project has no URL");
        $bundle = Bundle::bundleWithURL($url);
        $extractor = new LocalizationExtractor($bundle, $this->languages);
        $extractor->extract();
    }
}
