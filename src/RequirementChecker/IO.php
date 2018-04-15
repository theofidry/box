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
final class IO
{
    const VERBOSITY_QUIET = 16;
    const VERBOSITY_NORMAL = 32;
    const VERBOSITY_VERBOSE = 64;
    const VERBOSITY_VERY_VERBOSE = 128;
    const VERBOSITY_DEBUG = 256;

    private $shortParam = array();
    private $longParam = array();
    private $interactive;
    private $verbosity = self::VERBOSITY_NORMAL;
    private $colorSupport;

    public function __construct()
    {
        $this->parseInput();

        $shellVerbosity = $this->configureVerbosity();

        $this->interactive = $this->checkInteractivity($shellVerbosity);
        $this->colorSupport = $this->checkColorSupport();
    }

    /**
     * @return bool
     */
    public function isInteractive()
    {
        return $this->interactive;
    }

    /**
     * @return int
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * @param int $verbosity
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * @return bool
     */
    public function hasColorSupport()
    {
        return $this->colorSupport;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameterOption($values)
    {
        $values = (array) $values;

        foreach ($values as $value) {
            if (0 === strpos($value, '--')) {
                if (array_key_exists(substr($value, 2), $this->longParam)) {
                    return true;
                }

                continue;
            }

            if (0 === strpos($value, '-')) {
                if (array_key_exists(substr($value, 1), $this->shortParam)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterOption($values, $default = false)
    {
        $values = (array) $values;

        foreach ($values as $value) {
            if (0 === strpos($value, '--')) {
                if (array_key_exists($value = substr($value, 2), $this->longParam)) {
                    return $this->longParam[$value];
                }

                break;
            }

            if (0 === strpos($value, '-')) {
                if (array_key_exists($value = substr($value, 1), $this->shortParam)) {
                    return $this->shortParam[$value];
                }

                break;
            }
        }

        return $default;
    }

    /**
     * @return array
     */
    private function parseInput()
    {
        foreach ($_SERVER['argv'] as $token) {
            if (0 === strpos($token, '--')) {
                list($name, $value) = $this->parseLongOption($token);
                $this->longParam[$name] = $value;

                continue;
            }

            if ('-' === $token[0] && '-' !== $token) {
                list($name, $value) = $this->parseShortOption($token);
                $this->shortParam[$name] = $value;
            }
        }
    }

    /**
     * @param string $token
     *
     * @return array
     */
    private function parseLongOption($token)
    {
        $name = trim(substr($token, 2));

        if (false !== $pos = strpos($name, '=')) {
            if (0 === strlen($value = substr($name, $pos + 1))) {
                array_unshift($this->longParam, $value);
            }

            return array(substr($name, 0, $pos), $value);
        }

        if (1 === preg_match('/^--(?<name>[\p{L}\d]+?) +(?<value>[\p{L}\d]+?)$/u', $token, $matches)) {
            return array($matches['name'], $matches['value']);
        }

        return array($name, null);
    }

    /**
     * @param string $token
     *
     * @return array
     */
    private function parseShortOption($token)
    {
        $name = substr($token, 1);

        return array($name, null);
    }

    /**
     * @param int $shellVerbosity
     *
     * @return bool
     */
    private function checkInteractivity($shellVerbosity)
    {
        if (-1 === $shellVerbosity) {
            return false;
        }

        if (true === $this->hasParameterOption(array('--no-interaction', '-n'))) {
            return false;
        }

        if (function_exists('posix_isatty')) {
            if (!@posix_isatty(STDOUT) && false === getenv('SHELL_INTERACTIVE')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int
     */
    private function configureVerbosity()
    {
        switch ($shellVerbosity = (int) getenv('SHELL_VERBOSITY')) {
            case -1:
                $this->verbosity = self::VERBOSITY_QUIET;
                break;
            case 1:
                $this->verbosity = self::VERBOSITY_VERBOSE;
                break;
            case 2:
                $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
                break;
            case 3:
                $this->verbosity = self::VERBOSITY_DEBUG;
                break;
            default:
                $shellVerbosity = 0;
                break;
        }

        if ($this->hasParameterOption(array('--quiet', '-q'))) {
            $this->verbosity = self::VERBOSITY_QUIET;
            $shellVerbosity = -1;
        } else {
            if ($this->hasParameterOption('-vvv')
                || $this->hasParameterOption('--verbose=3')
                || '3' === $this->getParameterOption('--verbose', false)
            ) {
                $this->verbosity = self::VERBOSITY_DEBUG;
                $shellVerbosity = 3;
            } elseif ($this->hasParameterOption('-vv')
                || $this->hasParameterOption('--verbose=2')
                || '2' === $this->getParameterOption('--verbose', false)
            ) {
                $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
                $shellVerbosity = 2;
            } elseif ($this->hasParameterOption('-v')
                || $this->hasParameterOption('--verbose=1')
                || $this->hasParameterOption('--verbose')
                || $this->getParameterOption('--verbose', false)
            ) {
                $this->verbosity = self::VERBOSITY_VERBOSE;
                $shellVerbosity = 1;
            }
        }

        return $shellVerbosity;
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * Colorization is disabled if not supported by the stream:
     *
     *  -  Windows != 10.0.10586 without Ansicon, ConEmu or Mintty
     *  -  non tty consoles
     *
     * @return bool true if the stream supports colorization, false otherwise
     *
     * @see \Symfony\Component\Console\Output\StreamOutput
     */
    private function checkColorSupport()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return
                '10.0.10586' === PHP_WINDOWS_VERSION_MAJOR . '.' . PHP_WINDOWS_VERSION_MINOR . '.' . PHP_WINDOWS_VERSION_BUILD
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}