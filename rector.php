<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\FunctionLike\FunctionLikeToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . "/src",
        ])->withPhpSets()->withSkip([
            SensitiveConstantNameRector::class,
            ClassPropertyAssignToConstructorPromotionRector::class,
            ExplicitBoolCompareRector::class,
            FlipTypeControlToUseExclusiveTypeRector::class,
            DisallowedEmptyRuleFixerRector::class,
            LocallyCalledStaticMethodToNonStaticRector::class,
            RemoveUnusedPrivateMethodRector::class,
            RemoveUnusedPrivateMethodParameterRector::class,
            RemoveUselessReturnTagRector::class,
            RemoveUselessParamTagRector::class,
            RemoveUnusedVariableAssignRector::class,
            ExplicitReturnNullRector::class,
            RestoreDefaultNullToNullableTypePropertyRector::class,
            RemoveUnusedConstructorParamRector::class => [
                __DIR__ . "/src/FileWriters/SubclassFileWriter.php"
            ],
            RemoveUnusedPrivatePropertyRector::class => [
                __DIR__ . "/src/FileWriters/SubclassFileWriter.php"
            ],
            FunctionLikeToFirstClassCallableRector::class => [
                __DIR__ . "/src/FileWriters/LatteRenderer.php"
            ]
        ])->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true);
} catch (InvalidConfigurationException $e) {
    error_log($e->getMessage());
}
