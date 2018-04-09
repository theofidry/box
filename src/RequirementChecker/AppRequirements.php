<?php

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KevinGH\Box\RequirementChecker;

use function array_diff;
use function array_diff_key;
use function array_intersect_key;
use function array_key_exists;
use function file_exists;
use function json_last_error_msg;
use function sprintf;
use function str_replace;
use function substr;
use Symfony\Requirements\RequirementCollection;
use UnexpectedValueException;

/**
 * Collect the list of requirements for running the application. Code in this file must be PHP 5.3+ compatible as is used to know if the
 * application can be run.
 */
final class AppRequirements extends RequirementCollection
{
    private const SELF_PACKAGE = '__APPLICATION__';

    /**
     * @param string $composerJson Path to the `composer.json` file
     *
     * @return self Configured requirements
     */
    public static function create($composerJson)
    {
        $requirements = new self();

        $composerLockContents = self::retrieveComposerLockContents($composerJson);

        self::configurePhpVersionRequirements($requirements, $composerLockContents);
        self::configureExtensionRequirements($requirements, $composerLockContents);

        return $requirements;
    }

    /**
     * @param self $requirements
     * @param array $composerLockContents
     */
    private static function configurePhpVersionRequirements($requirements, $composerLockContents)
    {
        $installedPhpVersion = phpversion();

        if (isset($composerLockContents['platform']['php'])) {
            $requiredPhpVersion = $composerLockContents['platform']['php'];

            $requirements->addRequirement(
                version_compare(phpversion(), $requiredPhpVersion, '>='),
                sprintf(
                    'The application requires the version "%s" or greater. Got "%s"',
                    $requiredPhpVersion,
                    $installedPhpVersion
                ),
                '',
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

            if (null !== $requiredPhpVersion) {
                continue;
            }

            $requirements->addRequirement(
                version_compare(phpversion(), $requiredPhpVersion, '>='),
                sprintf(
                    'The package "%s" requires the version "%s" or greater. Got "%s"',
                    $packageInfo['name'],
                    $requiredPhpVersion,
                    $installedPhpVersion
                ),
                '',
                sprintf(
                    'The package "%s" requires the version "%s" or greater.',
                    $packageInfo['name'],
                    $requiredPhpVersion
                )
            );
        }
    }

    /**
     * @param self $requirements
     * @param array $composerLockContents
     */
    private static function configureExtensionRequirements($requirements, $composerLockContents)
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
                    extension_loaded($extension),
                    $message,
                    '',
                    $helpMessage
                );
            }
        }
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill `symfony/polyfill-mbstring` is provided
     * then the extension `ext-mbstring` will not be required.
     *
     * @param array $composerLockContents
     *
     * @return array Associative array containing the list of extensions required
     */
    private static function collectExtensionRequirements($composerLockContents)
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

            if (preg_match('/symfony\/polyfill-(?<extension>.+)/', $packageInfo['name'], $matches)) {
                $extension = $matches['extension'];

                if ('php' !== substr($extension, 0, 3)) {
                    $polyfills[$extension] = true;
                }
            }

            foreach ($packageRequire as $package => $constraint) {
                if (preg_match('/^ext-(?<extension>.+)$/', $package, $matches)) {
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

    /**
     * @param string $composerJson
     *
     * @throws UnexpectedValueException
     *
     * @return array Associative array containing the application platform requirements
     */
    private static function retrieveComposerLockContents($composerJson)
    {
        $composerLock = str_replace('.json', '.lock', $composerJson);

        self::checkFileExists($composerLock);

        return self::decodeJson($composerLock);
    }

    /**
     * @param string $file
     *
     * @throws UnexpectedValueException When the file does not exists
     */
    private static function checkFileExists($file)
    {
        if (false === $file) {
            throw new UnexpectedValueException(
                sprintf(
                    'Could not locate the file "%s"',
                    json_last_error_msg()
                )
            );
        }
    }

    /**
     * @param string $file
     *
     * @throws UnexpectedValueException When the file could not be decoded
     *
     * @return string Decoded file contents
     */
    private static function decodeJson($file)
    {
        $contents = @json_decode(file_get_contents($file), true);

        if (false === $contents) {
            throw new UnexpectedValueException(
                sprintf(
                    'Could not decode the JSON file "%s": %s',
                    $file,
                    json_last_error_msg()
                )
            );
        }

        return $contents;
    }
}
