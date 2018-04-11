<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;
use function dirname;
use function explode;
use function implode;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_relative;
use const PHP_EOL;
use function preg_match;
use function preg_quote;
use function preg_replace;
use Reflection;
use ReflectionClass;
use function serialize;
use function sprintf;
use function str_replace;
use Symfony\Requirements\Requirement;
use Symfony\Requirements\RequirementCollection;


/**
 * @private
 */
final class RequirementsDumper
{
    /**
     * @return string[][]
     */
    public static function dumpChecker(string $composerJson): array
    {
        $requirements = self::dumpRequirements($composerJson);

        $checker = str_replace(
            '__REQUIREMENTS_SERIALIZED_VALUE__',
            $requirements,
            file_contents((new ReflectionClass(Checker::class))->getFileName())
        );

        $checker = self::appendRequiredClasses(
            $checker,
            dirname($composerJson),
            RequirementCollection::class,
            Requirement::class
        );

        $checker .= <<<'PHP'

Checker::checkRequirements();

PHP;

        return [];
        return $checker;
    }

    private static function dumpRequirements(string $composerJson): string
    {
        $requirements = AppRequirementsFactory::create($composerJson);

        return serialize($requirements);
    }

    private static function appendRequiredClasses(string $checker, string $root, string ...$classes): string
    {
        $checker = preg_replace('/(namespace .+?;)/', '$1'.PHP_EOL.PHP_EOL.'__AUTOLOAD__', $checker);

        $autoload = [];

        foreach ($classes as $class) {
            $path = make_path_relative((new ReflectionClass($class))->getFileName(), $root);

            $autoload[] = sprintf("require '%s';", $path);
        }

        return str_replace(
            '__AUTOLOAD__',
            implode(PHP_EOL, $autoload),
            $checker
        );
    }

    private static function getClassFileContents(string $class): string
    {
        return file_contents((new ReflectionClass($class))->getFileName());
    }
}