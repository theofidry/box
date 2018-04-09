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

use function array_reduce;
use function function_exists;
use function getenv;
use const PHP_EOL;
use function posix_isatty;
use function str_pad;
use const STR_PAD_RIGHT;
use function str_repeat;
use function strlen;
use Symfony\Requirements\Requirement;
use function trim;
use function wordwrap;

//
// Code in this file must be PHP 5.3+ compatible as is used to know if Box can be run or not. It contains the necessary code to execute the
// check requirements.
//

/**
 * @param string $composerJson
 * @param bool   $verbose
 * @param bool   $debug
 *
 * @return bool
 */
function check_requirements($composerJson, $verbose, $debug)
{
    $lineSize = 70;
    $requirements = AppRequirements::create($composerJson);
    $iniPath = $requirements->getPhpIniPath();

    $checkPassed = array_reduce(
        $requirements->getRequirements(),
        /**
         * @param bool        $checkPassed
         * @param Requirement $requirement
         *
         * @return bool
         */
        function ($checkPassed, Requirement $requirement) use ($lineSize) {
            return $checkPassed && null === get_error_message($requirement, $lineSize);
        },
        false
    );

    if (false === $checkPassed) {
        // Override the default verbosity to output errors regardless of the verbosity asked by the user
        $verbose = true;
    }

    echo_title('Box Requirements Checker', 'title', $verbose);

    vecho('> PHP is using the following php.ini file:'.PHP_EOL, $verbose);

    if ($iniPath) {
        echo_style('green', '  '.$iniPath, $verbose);
    } else {
        echo_style('yellow', '  WARNING: No configuration file (php.ini) used by PHP!', $verbose);
    }

    vecho(PHP_EOL.PHP_EOL, $verbose);
    vecho('> Checking Box requirements:'.PHP_EOL.'  ', $verbose);

    $messages = [];

    foreach ($requirements->getRequirements() as $requirement) {
        if ($helpText = get_error_message($requirement, $lineSize)) {
            if ($debug) {
                echo_style('red', '✘ '.$requirement->getTestMessage().PHP_EOL.'  ', $verbose);
            } else {
                echo_style('red', 'E', $verbose);
                $messages['error'][] = $helpText;
            }
        } elseif ($debug) {
            echo_style('green', '✔ '.$requirement->getHelpText().PHP_EOL.'  ', $verbose);
        } else {
            echo_style('green', '.', $verbose);
        }
    }

    foreach ($requirements->getRecommendations() as $requirement) {
        if ($helpText = get_error_message($requirement, $lineSize)) {
            echo_style('yellow', 'W', $verbose);
            $messages['warning'][] = $helpText;
        } else {
            echo_style('green', '.', $verbose);
        }
    }

    if ($checkPassed) {
        echo_block($lineSize, 'success', 'OK', 'Your system is ready to run the application.', $verbose);
    } else {
        echo_block($lineSize, 'error', 'ERROR', 'Your system is not ready to run the application', $verbose);

        if (false === $debug) {
            echo_title('Fix the following mandatory requirements', 'red', $verbose);

            foreach ($messages['error'] as $helpText) {
                vecho(' * '.$helpText.PHP_EOL, $verbose);
            }
        }
    }

    if (!empty($messages['warning'])) {
        echo_title('Optional recommendations to improve your setup', 'yellow', $verbose);

        foreach ($messages['warning'] as $helpText) {
            vecho(' * '.$helpText.PHP_EOL, $verbose);
        }
    }

    return $checkPassed;
}

function vecho($message, $verbose)
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
 * @return string|null
 */
function get_error_message(Requirement $requirement, $lineSize)
{
    if ($requirement->isFulfilled()) {
        return null;
    }

    $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL.'   ').PHP_EOL;

    return $errorMessage;
}

/**
 * @param string      $title
 * @param string $style
 * @param bool        $verbose
 */
function echo_title($title, $style = null, $verbose)
{
    if (false === $verbose) {
        return;
    }

    echo PHP_EOL;
    echo_style($style, $title.PHP_EOL, $verbose);
    echo_style($style, str_repeat('=', strlen($title)).PHP_EOL, $verbose);
    echo PHP_EOL;
}

/**
 * @param string $style
 * @param string $message
 * @param bool   $verbose
 */
function echo_style($style, $message, $verbose)
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

    $supports = has_color_support();

    echo($supports ? $styles[$style] : '').$message.($supports ? $styles['reset'] : '');
}

/**
 * @param int $lineSize
 * @param string $style
 * @param string $title
 * @param string $message
 * @param bool   $verbose
 */
function echo_block($lineSize, $style, $title, $message, $verbose)
{
    if (false === $verbose) {
        return;
    }

    $message = str_pad(' ['.$title.'] '.trim($message).' ', $lineSize, ' ', STR_PAD_RIGHT);

    $width = $lineSize;

    echo PHP_EOL.PHP_EOL;

    echo_style($style, str_repeat(' ', $width), $verbose);
    echo PHP_EOL;
    echo_style($style, $message, $verbose);
    echo PHP_EOL;
    echo_style($style, str_repeat(' ', $width), $verbose);
    echo PHP_EOL;
}

/**
 * @return bool
 */
function has_color_support()
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
