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

use function array_diff_key;
use function array_key_exists;
use function array_map;
use function sprintf;
use function substr;

/**
 * Collect the list of requirements for running the application.
 *
 * @private
 */
final class AppRequirementsFactory
{
    private const SELF_PACKAGE = '__APPLICATION__';

    /**
     * @param array $composerLockDecodedContents Decoded JSON contents of the `composer.lock` file
     *
     * @return array Serialized configured requirements
     */
    public static function create(array $composerLockDecodedContents): array
    {
        $requirements = new RequirementCollection();

        self::configurePhpVersionRequirements($requirements, $composerLockDecodedContents);
        self::configureExtensionRequirements($requirements, $composerLockDecodedContents);

        return self::exportRequirementsIntoConfig($requirements);
    }

    private static function configurePhpVersionRequirements(RequirementCollection $requirements, array $composerLockContents): void
    {
        if (isset($composerLockContents['platform']['php'])) {
            $requiredPhpVersion = $composerLockContents['platform']['php'];

            $requirements->addRequirement(
                self::generatePhpCheckStatement((string) $requiredPhpVersion),
                sprintf(
                    'The application requires the version "%s" or greater.',
                    $requiredPhpVersion
                ),
                sprintf(
                    'The application requires the version "%s" or greater.',
                    $requiredPhpVersion
                )
            );

            return; // No need to check the packages requirements: the application platform config is the authority here
        }

        $packages = $composerLockContents['packages'] ?? [];

        foreach ($packages as $packageInfo) {
            $requiredPhpVersion = $packageInfo['require']['php'] ?? null;

            if (null === $requiredPhpVersion) {
                continue;
            }

            $requirements->addRequirement(
                self::generatePhpCheckStatement((string) $requiredPhpVersion),
                sprintf(
                    'The package "%s" requires the version "%s" or greater.',
                    $packageInfo['name'],
                    $requiredPhpVersion
                ),
                sprintf(
                    'The package "%s" requires the version "%s" or greater.',
                    $packageInfo['name'],
                    $requiredPhpVersion
                )
            );
        }
    }

    private static function configureExtensionRequirements(RequirementCollection $requirements, array $composerLockContents): void
    {
        $extensionRequirements = self::collectExtensionRequirements($composerLockContents);

        foreach ($extensionRequirements as $extension => $packages) {
            foreach ($packages as $package) {
                if (self::SELF_PACKAGE === $package) {
                    $message = sprintf(
                        'The application requires the extension "%s". Enable it or install a polyfill.',
                        $extension
                    );
                    $helpMessage = sprintf(
                        'The application requires the extension "%s".',
                        $extension
                    );
                } else {
                    $message = sprintf(
                        'The package "%s" requires the extension "%s". Enable it or install a polyfill.',
                        $package,
                        $extension
                    );
                    $helpMessage = sprintf(
                        'The package "%s" requires the extension "%s".',
                        $package,
                        $extension
                    );
                }

                $requirements->addRequirement(
                    self::generateExtensionCheckStatement($extension),
                    $message,
                    $helpMessage
                );
            }
        }
    }

    private static function exportRequirementsIntoConfig(RequirementCollection $requirements): array
    {
        return array_map(
            function (Requirement $requirement): array {
                return [
                    $requirement->getIsFullfilledChecker(),
                    $requirement->getTestMessage(),
                    $requirement->getHelpText(),
                ];
            },
            $requirements->getRequirements()
        );
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill `symfony/polyfill-mbstring` is provided
     * then the extension `ext-mbstring` will not be required.
     *
     * @param array $composerLockContents
     *
     * @return array Associative array containing the list of extensions required
     */
    private static function collectExtensionRequirements(array $composerLockContents): array
    {
        $requirements = [];
        $polyfills = [];

        $platform = $composerLockContents['platform'] ?? [];

        foreach ($platform as $package => $constraint) {
            if (preg_match('/^ext-(?<extension>.+)$/', $package, $matches)) {
                $extension = $matches['extension'];

                $requirements[$extension] = [self::SELF_PACKAGE];
            }
        }

        $packages = $composerLockContents['packages'] ?? [];

        foreach ($packages as $packageInfo) {
            $packageRequire = $packageInfo['require'] ?? [];

            if (1 === preg_match('/symfony\/polyfill-(?<extension>.+)/', $packageInfo['name'], $matches)) {
                $extension = $matches['extension'];

                if ('php' !== substr($extension, 0, 3)) {
                    $polyfills[$extension] = true;
                }
            }

            foreach ($packageRequire as $package => $constraint) {
                if (1 === preg_match('/^ext-(?<extension>.+)$/', $package, $matches)) {
                    $extension = $matches['extension'];

                    if (false === array_key_exists($extension, $requirements)) {
                        $requirements[$extension] = [];
                    }

                    $requirements[$extension][] = $packageInfo['name'];
                }
            }
        }

        return array_diff_key($requirements, $polyfills);
    }

    private static function generatePhpCheckStatement(string $requiredPhpVersion): string
    {
        return "return require_once 'Semver.php'; \\_HumbugBox\Composer\Semver::satisfies(preg_replace('#^([^~+-]+).*$#', '$1', \PHP_VERSION), '$requiredPhpVersion');";
    }

    private static function generateExtensionCheckStatement(string $extension): string
    {
        return "return \\extension_loaded('$extension');";
    }
}
