<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;
use function array_column;
use function array_map;
use function basename;
use function dirname;
use function explode;
use function implode;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\filename;
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
    public const CHECK_FILE_NAME = 'check_requirements.php';

    private const REQUIREMENTS_CHECKER_TEMPLATE = <<<'PHP'
<?php

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\RequirementChecker;

//__AUTOLOAD__

use KevinGH\Box\RequirementChecker\Checker;

$checkPassed = Checker::checkRequirements();

if (false === $checkPassed) {
    exit(1);
}

PHP;

    private const CLASSED_USED = [
        RequirementCollection::class,
        Requirement::class,
    ];


    /**
     * @return string[][]
     */
    public static function dump(string $composerJson): array
    {
        $filesWithContents = [
            self::dumpChecker($composerJson),
        ];

        foreach (self::CLASSED_USED as $class) {
            $filesWithContents[] = [
                self::retrieveFileShortName($class),
                self::retrieveClassFileContents($class),
            ];
        }

        $filesWithContents[] = self::dumpCheckScript(...array_column($filesWithContents, 0));

        return $filesWithContents;
    }

    public static function dumpChecker(string $composerJson): array
    {
        $requirements = self::dumpRequirements($composerJson);

        $file = self::retrieveFileShortName(Checker::class);
        $contents = self::retrieveClassFileContents(Checker::class);

        return [
            $file,
            str_replace(
                '__REQUIREMENTS_SERIALIZED_VALUE__',
                $requirements,
                $contents
            )
        ];
    }

    public static function dumpCheckScript(string ...$files): array
    {
        $autoloads = array_map(
            function (string $file): string {
                return sprintf(
                    'require_once __DIR__."/%s";',
                    $file
                );
            },
            $files
        );

        $autoloadStmt = implode(PHP_EOL, $autoloads);

        return [
            self::CHECK_FILE_NAME,
            str_replace(
                '//__AUTOLOAD__',
                $autoloadStmt,
                self::REQUIREMENTS_CHECKER_TEMPLATE
            )
        ];
    }

    private static function dumpRequirements(string $composerJson): string
    {
        $requirements = AppRequirementsFactory::create($composerJson);

        return serialize($requirements);
    }

    private static function retrieveClassFileContents(string $class): string
    {
        return file_contents((new ReflectionClass($class))->getFileName());
    }

    private static function retrieveFileShortName(string $class): string
    {
        return filename(
            (new ReflectionClass($class))->getFileName()
        );
    }
}