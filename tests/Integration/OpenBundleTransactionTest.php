<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Bundles\OpenBundleTransaction;
use App\Bundles\ProjectBundleLoader;
use App\Tests\Support\CoreDataTestCase;
use Exception;

final class OpenBundleTransactionTest extends CoreDataTestCase
{
    /**
     * @throws Exception
     */
    public function testExecuteLoadsTheProjectFromAScaffoldedBundle(): void
    {
        $project = $this->makeProject();
        $bundleURL = $project->url ?? self::fail("Project has no url");
        $model = $project->model ?? self::fail("Project has no model");
        $this->makeEntity($model, "Widget");
        $this->context->save();
        new BundleScaffolder($bundleURL, $project, new BundleGenerationOptions())->scaffold();

        $transaction = new OpenBundleTransaction(new ProjectBundleLoader($bundleURL, $this->context));
        $transaction->execute();

        self::assertSame($bundleURL->lastPathComponent, $transaction->project->name, "the loaded project takes its name from the bundle");
        self::assertSame($bundleURL->absoluteString, $transaction->project->url?->absoluteString);
    }
}
