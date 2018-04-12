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

use Symfony\Requirements\Requirement;

/**
 * The code in this file must be PHP 5.3+ compatible as is used to know if the application can be run.
 *
 * @private
 */
final class Checker
{
    /** @private */
    const REQUIREMENTS_CONFIG = '.requirements.php';

    /**
     * @return bool
     */
    public static function checkRequirements()
    {
        $config = require self::REQUIREMENTS_CONFIG;

        $requirements = new LazyRequirementCollection();

        foreach ($config as $constraint) {
            call_user_func_array(array($requirements, 'addLazyRequirement'), $constraint);
        }

        list($verbose, $debug) = self::retrieveConfig();

        return self::check($requirements, $verbose, $debug);
    }

    /**
     * @return bool[] The first value is the verbosity and the second is wether debug is enabled or not
     */
    private static function retrieveConfig()
    {
        return array(true, true);
    }

    /**
     * @param LazyRequirementCollection $requirements
     * @param bool                  $verbose
     * @param bool                  $debug
     *
     * @return bool
     */
    private static function check(LazyRequirementCollection $requirements, $verbose, $debug)
    {
        $lineSize = 70;
        $iniPath = $requirements->getPhpIniPath();

        $checkPassed = self::evaluateRequirements($requirements);

        if (false === $checkPassed) {
            // Override the default verbosity to output errors regardless of the verbosity asked by the user
            $verbose = true;
        }

        self::echoTitle('Box Requirements Checker', 'title', $verbose);

        self::_print('> PHP is using the following php.ini file:'.PHP_EOL, $verbose);

        if ($iniPath) {
            self::echo_style('green', '  '.$iniPath, $verbose);
        } else {
            self::echo_style('yellow', '  WARNING: No configuration file (php.ini) used by PHP!', $verbose);
        }

        self::_print(PHP_EOL.PHP_EOL, $verbose);
        self::_print('> Checking Box requirements:'.PHP_EOL.'  ', $verbose);

        $messages = array();

        foreach ($requirements->getRequirements() as $requirement) {
            if ($helpText = self::getErrorMessage($requirement, $lineSize)) {
                if ($debug) {
                    self::echo_style('red', '✘ '.$requirement->getTestMessage().PHP_EOL.'  ', $verbose);
                } else {
                    self::echo_style('red', 'E', $verbose);
                    $messages['error'][] = $helpText;
                }
            } elseif ($debug) {
                self::echo_style('green', '✔ '.$requirement->getHelpText().PHP_EOL.'  ', $verbose);
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
                    self::_print(' * '.$helpText.PHP_EOL, $verbose);
                }
            }
        }

        if (!empty($messages['warning'])) {
            self::echoTitle('Optional recommendations to improve your setup', 'yellow', $verbose);

            foreach ($messages['warning'] as $helpText) {
                self::_print(' * '.$helpText.PHP_EOL, $verbose);
            }
        }

        return $checkPassed;
    }

    /**
     * @return bool
     */
    private static function evaluateRequirements(LazyRequirementCollection $requirements)
    {
        return array_reduce(
            $requirements->getRequirements(),
            /**
             * @param bool        $checkPassed
             * @param Requirement $requirement
             *
             * @return bool
             */
            function ($checkPassed, Requirement $requirement) {
                return $checkPassed && $requirement->isFulfilled();
            },
            true
        );
    }

    private static function _print($message, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        echo $message;
    }

    /**
     * @param Requirement $requirement
     * @param int         $lineSize
     *
     * @return null|string
     */
    private static function getErrorMessage(Requirement $requirement, $lineSize)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL.'   ').PHP_EOL;

        return $errorMessage;
    }

    /**
     * @param string $title
     * @param string $style
     * @param bool   $verbose
     */
    private static function echoTitle($title, $style, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        echo PHP_EOL;
        self::echo_style($style, $title.PHP_EOL, $verbose);
        self::echo_style($style, str_repeat('=', strlen($title)).PHP_EOL, $verbose);
        echo PHP_EOL;
    }

    /**
     * @param string $style
     * @param string $message
     * @param bool   $verbose
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

        echo($supports ? $styles[$style] : '').$message.($supports ? $styles['reset'] : '');
    }

    /**
     * @param int    $lineSize
     * @param string $style
     * @param string $title
     * @param string $message
     * @param bool   $verbose
     */
    private static function echo_block($lineSize, $style, $title, $message, $verbose)
    {
        if (false === $verbose) {
            return;
        }

        $message = str_pad(' ['.$title.'] '.trim($message).' ', $lineSize, ' ', STR_PAD_RIGHT);

        $width = $lineSize;

        echo PHP_EOL.PHP_EOL;

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
            if (DIRECTORY_SEPARATOR === '\\') {
                $support = false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
            } else {
                $support = function_exists('posix_isatty') && @posix_isatty(STDOUT);
            }
        }

        return $support;
    }
}
