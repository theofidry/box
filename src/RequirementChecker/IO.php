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
    
    private $tokens;
    private $parsed;
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
     * @return array
     */
    private function parseInput()
    {
        $this->tokens = $_SERVER['argv'];

        $parsed = $this->tokens;

        while (null !== $token = array_shift($this->tokens)) {
            if ('' === $token) {
                continue;
            } elseif (0 === strpos($token, '--')) {
                list($name, $value) = $this->parseLongOption($token);
                $parsed[$name] = $value;
            } elseif ('-' === $token[0] && '-' !== $token) {
                list($name, $value) = $this->parseShortOption($token);
                $parsed[$name] = $value;
            }
        }

        $this->parsed = $parsed;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    private function parseLongOption($token)
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '=')) {
            if (0 === strlen($value = substr($name, $pos + 1))) {
                array_unshift($this->parsed, $value);
            }

            return array(substr($name, 0, $pos), $value);
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
     * {@inheritdoc}
     */
    public function hasParameterOption($values, $onlyParams = false)
    {
        $values = (array) $values;

        foreach ($this->parsed as $token) {
            if ($onlyParams && '--' === $token) {
                return false;
            }
            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value.'=')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterOption($values, $default = false, $onlyParams = false)
    {
        $values = (array) $values;
        $tokens = $this->parsed;

        while (0 < count($tokens)) {
            $token = array_shift($tokens);
            if ($onlyParams && '--' === $token) {
                return false;
            }

            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value.'=')) {
                    if (false !== $pos = strpos($token, '=')) {
                        return substr($token, $pos + 1);
                    }

                    return array_shift($tokens);
                }
            }
        }

        return $default;
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

        if (true === $this->hasParameterOption(array('--no-interaction', '-n'), true)) {
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
                break;
            default:
                $shellVerbosity = 0;
                break;
        }

        if (true === $this->hasParameterOption(array('--quiet', '-q'), true)) {
            $this->verbosity = self::VERBOSITY_QUIET;
            $shellVerbosity = -1;
        } else {
            if ($this->hasParameterOption('-vvv', true)
                || $this->hasParameterOption('--verbose=3', true)
                || 3 === $this->getParameterOption('--verbose', false, true)
            ) {
                $this->verbosity = self::VERBOSITY_DEBUG;
                $shellVerbosity = 3;
            } elseif ($this->hasParameterOption('-vv', true)
                || $this->hasParameterOption('--verbose=2', true)
                || 2 === $this->getParameterOption('--verbose', false, true)
            ) {
                $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
                $shellVerbosity = 2;
            } elseif ($this->hasParameterOption('-v', true)
                || $this->hasParameterOption('--verbose=1', true)
                || $this->hasParameterOption('--verbose', true)
                || $this->getParameterOption('--verbose', false, true)
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