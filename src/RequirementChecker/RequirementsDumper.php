<?php

declare(strict_types=1);

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

use ReflectionClass;
use const PHP_EOL;
use function array_column;
use function array_map;
use function implode;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\filename;
use function sprintf;
use function str_replace;
use Symfony\Component\Console\Terminal;
use function var_export;

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

    private const REQUIREMENTS_CONFIG_TEMPLATE = <<<'PHP'
<?php

return __CONFIG__;
PHP;

    private const CLASSED_USED = [
        Checker::class,
        IO::class,
        Printer::class,
        Requirement::class,
        RequirementCollection::class,
        Terminal::class,
    ];

    /**
     * @return string[][]
     */
    public static function dump(string $composerJson): array
    {
        $filesWithContents = [
            self::dumpRequirementsConfig($composerJson),
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

    private static function dumpRequirementsConfig(string $composerJson): array
    {
        $config = AppRequirementsFactory::create($composerJson);

        return [
            Checker::REQUIREMENTS_CONFIG,
            str_replace(
                '__CONFIG__',
                var_export($config, true),
                self::REQUIREMENTS_CONFIG_TEMPLATE
            ),
        ];
    }

    private static function dumpCheckScript(string ...$files): array
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
            ),
        ];
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
