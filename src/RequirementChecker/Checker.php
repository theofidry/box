<?php

/*
 * This file is part of the humbug/Box package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KevinGH\Box\RequirementChecker;

use function file_exists;
use LogicException;
use Symfony\Requirements\Requirement;
use const PHP_EOL;
use const STR_PAD_RIGHT;
use function array_reduce;
use function function_exists;
use function getenv;
use function posix_isatty;
use function str_pad;
use function str_repeat;
use function strlen;
use Symfony\Requirements\RequirementCollection;
use function trim;
use function unserialize;
use function wordwrap;

/**
 * The code in this file must be PHP 5.3+ compatible as is used to know if the application can be run.
 *
 * @private
 */
final class Checker
{
    public const REQUIREMENTS_CONFIG = '.requirements.php';

    public static function checkRequirements(): bool
    {
        $config = require self::REQUIREMENTS_CONFIG;

        $requirements = new RequirementCollection();

        foreach ($config as $constraint) {
            $requirements->addRequirement(...$constraint);
        }

        [$verbose, $debug] = self::retrieveConfig();

        return self::check($requirements, $verbose, $debug);
    }

    /**
     * @return bool[] The first value is the verbosity and the second is wether debug is enabled or not
     */
    private static function retrieveConfig()
    {
        return [true, true];

        if (true === $input->hasParameterOption(array('--ansi'), true)) {
            $output->setDecorated(true);
        } elseif (true === $input->hasParameterOption(array('--no-ansi'), true)) {
            $output->setDecorated(false);
        }

        if (true === $input->hasParameterOption(array('--no-interaction', '-n'), true)) {
            $input->setInteractive(false);
        } elseif (function_exists('posix_isatty')) {
            $inputStream = null;

            if ($input instanceof StreamableInputInterface) {
                $inputStream = $input->getStream();
            }

            if (!@posix_isatty($inputStream) && false === getenv('SHELL_INTERACTIVE')) {
                $input->setInteractive(false);
            }
        }

        switch ($shellVerbosity = (int)getenv('SHELL_VERBOSITY')) {
            case -1:
                $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
                break;
            case 1:
                $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
                break;
            case 2:
                $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
                break;
            case 3:
                $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
                break;
            default:
                $shellVerbosity = 0;
                break;
        }

        if (true === $input->hasParameterOption(array('--quiet', '-q'), true)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            $shellVerbosity = -1;
        } else {
            if ($input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || 3 === $input->getParameterOption('--verbose', false, true)) {
                $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
                $shellVerbosity = 3;
            } elseif ($input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || 2 === $input->getParameterOption('--verbose', false, true)) {
                $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
                $shellVerbosity = 2;
            } elseif ($input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true) || $input->getParameterOption('--verbose', false, true)) {
                $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
                $shellVerbosity = 1;
            }
        }

        if (-1 === $shellVerbosity) {
            $input->setInteractive(false);
        }

        putenv('SHELL_VERBOSITY=' . $shellVerbosity);
        $_ENV['SHELL_VERBOSITY'] = $shellVerbosity;
        $_SERVER['SHELL_VERBOSITY'] = $shellVerbosity;
    }

    /**
     * @param RequirementCollection $requirements
     * @param bool $verbose
     * @param bool $debug
     *
     * @return bool
     */
    private static function check(RequirementCollection $requirements, $verbose, $debug)
    {
        $lineSize = 70;
        $iniPath = $requirements->getPhpIniPath();

        $checkPassed = self::evaluateRequirements($requirements, $lineSize);

        if (false === $checkPassed) {
            // Override the default verbosity to output errors regardless of the verbosity asked by the user
            $verbose = true;
        }

        self::echoTitle('Box Requirements Checker', 'title', $verbose);

        self::print('> PHP is using the following php.ini file:' . PHP_EOL, $verbose);

        if ($iniPath) {
            self::echo_style('green', '  ' . $iniPath, $verbose);
        } else {
            self::echo_style('yellow', '  WARNING: No configuration file (php.ini) used by PHP!', $verbose);
        }

        self::print(PHP_EOL . PHP_EOL, $verbose);
        self::print('> Checking Box requirements:' . PHP_EOL . '  ', $verbose);

        $messages = [];

        foreach ($requirements->getRequirements() as $requirement) {
            if ($helpText = self::getErrorMessage($requirement, $lineSize)) {
                if ($debug) {
                    self::echo_style('red', '✘ ' . $requirement->getTestMessage() . PHP_EOL . '  ', $verbose);
                } else {
                    self::echo_style('red', 'E', $verbose);
                    $messages['error'][] = $helpText;
                }
            } elseif ($debug) {
                self::echo_style('green', '✔ ' . $requirement->getHelpText() . PHP_EOL . '  ', $verbose);
            } else {
                self::echo_style('green', '.', $verbose);
            }
        }

        foreach ($requirements->getRecommendations() as $requirement) {
            if ($helpText = self::getErrorMessage($requirement, $lineSize)) {
                self::echo_style('yellow', 'W', $verbose);
                $messages['warning'][] = $helpText;
            } else {
                self::echo_style('green', '.', $verbose);
            }
        }

        if ($checkPassed) {
            self::echo_block($lineSize, 'success', 'OK', 'Your system is ready to run the application.', $verbose);
        } else {
            self::echo_block($lineSize, 'error', 'ERROR', 'Your system is not ready to run the application', $verbose);

            if (false === $debug) {
                self::echoTitle('Fix the following mandatory requirements', 'red', $verbose);

                foreach ($messages['error'] as $helpText) {
                    self::print(' * ' . $helpText . PHP_EOL, $verbose);
                }
            }
        }

        if (!empty($messages['warning'])) {
            self::echoTitle('Optional recommendations to improve your setup', 'yellow', $verbose);

            foreach ($messages['warning'] as $helpText) {
                self::print(' * ' . $helpText . PHP_EOL, $verbose);
            }
        }

        return $checkPassed;
    }

    /**
     * @param RequirementCollection $requirements
     * @param int $lineSize
     * 
     * @return bool
     */
    private static function evaluateRequirements(RequirementCollection $requirements, $lineSize)
    {
        return array_reduce(
            $requirements->getRequirements(),
            /**
             * @param bool $checkPassed
             * @param Requirement $requirement
             *
             * @return bool
             */
            function ($checkPassed, Requirement $requirement) use ($lineSize) {
                return $checkPassed && null === self::getErrorMessage($requirement, $lineSize);
            },
            false
        );
    }

    private static function print($message, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        echo $message;
    }

    /**
     * @param Requirement $requirement
     * @param int $lineSize
     *
     * @return string|null
     */
    private static function getErrorMessage(Requirement $requirement, $lineSize)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL . '   ') . PHP_EOL;

        return $errorMessage;
    }

    /**
     * @param string $title
     * @param string $style
     * @param bool $verbose
     */
    private static function echoTitle($title, $style = null, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        echo PHP_EOL;
        self::echo_style($style, $title . PHP_EOL, $verbose);
        self::echo_style($style, str_repeat('=', strlen($title)) . PHP_EOL, $verbose);
        echo PHP_EOL;
    }

    /**
     * @param string $style
     * @param string $message
     * @param bool $verbose
     */
    private static function echo_style($style, $message, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        // ANSI color codes
        $styles = array(
            'reset' => "\033[0m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'error' => "\033[37;41m",
            'success' => "\033[30;42m",
        );

        $styles['title'] = $styles['yellow'];

        $supports = self::hasColorSupports();

        echo ($supports ? $styles[$style] : '') . $message . ($supports ? $styles['reset'] : '');
    }

    /**
     * @param int $lineSize
     * @param string $style
     * @param string $title
     * @param string $message
     * 
     * @param bool $verbose
     */
    private static function echo_block($lineSize, $style, $title, $message, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        $message = str_pad(' [' . $title . '] ' . trim($message) . ' ', $lineSize, ' ', STR_PAD_RIGHT);

        $width = $lineSize;

        echo PHP_EOL . PHP_EOL;

        self::echo_style($style, str_repeat(' ', $width), $verbose);
        echo PHP_EOL;
        self::echo_style($style, $message, $verbose);
        echo PHP_EOL;
        self::echo_style($style, str_repeat(' ', $width), $verbose);
        echo PHP_EOL;
    }

    /**
     * @return bool
     */
    private static function hasColorSupports()
    {
        static $support;

        if (null === $support) {
            if (DIRECTORY_SEPARATOR == '\\') {
                $support = false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
            } else {
                $support = function_exists('posix_isatty') && @posix_isatty(STDOUT);
            }
        }

        return $support;
    }
}
