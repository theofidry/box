<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;
use function KevinGH\Box\FileSystem\file_contents;
use Reflection;
use ReflectionClass;
use function serialize;
use function str_replace;


/**
 * @private
 */
final class RequirementsDumper
{
    public static function dumpChecker(string $composerJson): string
    {
        $requirements = self::dumpRequirements($composerJson);

        $checker = str_replace(
            '__REQUIREMENTS_SERIALIZED_VALUE__',
            $requirements,
            self::getClassFileContents(Checker::class)
        );

        $checker .= <<<'PHP'


Checker::checkRequirements();

PHP;

        return $checker;
    }

    private static function dumpRequirements(string $composerJson): string
    {
        $requirements = AppRequirementsFactory::create($composerJson);

        return serialize($requirements);
    }

    private static function getClassFileContents(string $class): string
    {
        return file_contents((new ReflectionClass($class))->getFileName());
    }
}