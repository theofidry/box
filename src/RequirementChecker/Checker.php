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
        $requirements = self::retrieveRequirements();

        $checkPassed = $requirements->evaluateRequirements();

        $io = new InputOutput();

        if (false === $checkPassed) {
            // Override the default verbosity to output errors regardless of the verbosity asked by the user
            $io->setVerbosity(InputOutput::VERBOSITY_VERY_VERBOSE);
        }

        self::printCheck(
            new Printer(
                $io->getVerbosity(),
                $io->hasColorSupport()
            ),
            $requirements
        );

        return $checkPassed;
    }

    public static function printCheck(Printer $printer, RequirementCollection $requirements)
    {
        $verbosity = InputOutput::VERBOSITY_VERY_VERBOSE;

        $iniPath = $requirements->getPhpIniPath();

        $printer->title('Box Requirements Checker', $verbosity);

        $printer->println('> PHP is using the following php.ini file:', $verbosity);

        if ($iniPath) {
            $printer->println('  '.$iniPath, $verbosity, 'green');
        } else {
            $printer->println('  WARNING: No configuration file (php.ini) used by PHP!', $verbosity, 'yellow');
        }

        $printer->println('', $verbosity);

        if (count($requirements) > 0) {
            $printer->println('> Checking Box requirements:', $verbosity);
            $printer->print('  ', $verbosity);
        } else {
            $printer->println('> No requirements found.', $verbosity);
        }

        $errorMessages = array();

        foreach ($requirements->getRequirements() as $requirement) {
            if ($errorMessage = $printer->getRequirementErrorMessage($requirement)) {
                if ($printer->getVerbosity() === InputOutput::VERBOSITY_DEBUG) {
                    $printer->println('✘ '.$requirement->getTestMessage(), InputOutput::VERBOSITY_DEBUG, 'red');
                    $printer->print('  ', InputOutput::VERBOSITY_DEBUG);
                } else {
                    $printer->print('E', $verbosity, 'red');
                    $errorMessages[] = $errorMessage;
                }

                continue;
            }

            if ($printer->getVerbosity() === InputOutput::VERBOSITY_DEBUG) {
                $printer->println('✔ '.$requirement->getHelpText(), InputOutput::VERBOSITY_DEBUG, 'green');
                $printer->print('  ', InputOutput::VERBOSITY_DEBUG);
            } else {
                $printer->print('.', $verbosity, 'green');
            }
        }

        if ($printer->getVerbosity() !== InputOutput::VERBOSITY_DEBUG && count($requirements) > 0) {
            $printer->println('', $verbosity);
        }

        if ($requirements->evaluateRequirements()) {
            $printer->block('OK', 'Your system is ready to run the application.', $verbosity, 'success');
        } else {
            $printer->block('ERROR', 'Your system is not ready to run the application.', $verbosity, 'error');

            $printer->title('Fix the following mandatory requirements:', $verbosity, 'red');

            foreach ($errorMessages as $errorMessage) {
                $printer->println(' * '.$errorMessage, $verbosity);
            }
        }
    }

    /**
     * @return RequirementCollection
     */
    private static function retrieveRequirements()
    {
        $config = require self::REQUIREMENTS_CONFIG;

        $requirements = new RequirementCollection();

        foreach ($config as $constraint) {
            call_user_func_array(array($requirements, 'addRequirement'), $constraint);
        }

        return $requirements;
    }
}
