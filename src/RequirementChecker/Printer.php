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

use const PHP_EOL;
use function sprintf;
use Symfony\Component\Console\Terminal;

/**
 * The code in this file must be PHP 5.3+ compatible as is used to know if the application can be run.
 *
 * @private
 */
final class Printer
{
    private $styles = array(
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'title' => "\033[33m",
        'error' => "\033[37;41m",
        'success' => "\033[30;42m",
    );
    private $verbosity;
    private $supportColors;
    private $with;

    /**
     * @param int $verbosity
     * @param bool $supportColors
     * @param int|null $with
     */
    public function __construct($verbosity, $supportColors, $with = null)
    {
        if (null === $with) {
            $with = (new Terminal())->getWidth();
        }

        $this->verbosity = $verbosity;
        $this->supportColors = $supportColors;
        $this->with = $with;
    }

    /**
     * @return int
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * @param string $title
     * @param int $verbosity
     * @param string|null $style
     */
    public function title($title, $verbosity, $style = null)
    {
        if (null === $style) {
            $style = 'title';
        }

        $this->println('', $verbosity, $style);
        $this->println($title, $verbosity, $style);
        $this->println(str_repeat('=', strlen($title)), $verbosity, $style);
        $this->println('', $verbosity, $style);
    }

    /**
     * @param Requirement $requirement
     *
     * @return null|string
     */
    public function getRequirementErrorMessage(Requirement $requirement)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $this->with - 3, PHP_EOL.'   ').PHP_EOL;

        return $errorMessage;
    }

    /**
     * @param string $title
     * @param string $message
     * @param int   $verbosity
     * @param string|null $style
     */
    public function block($title, $message, $verbosity, $style = null)
    {
        $message = str_pad(
            ' ['.$title.'] '.trim($message).' ', $this->with,
            ' ',
            STR_PAD_RIGHT
        );

        $this->println('', $verbosity);
        $this->println(str_repeat(' ', $this->with), $verbosity, $style);
        $this->println($message, $verbosity, $style);
        $this->println(str_repeat(' ', $this->with), $verbosity, $style);
    }

    /**
     * @param string $message
     * @param int   $verbosity
     * @param string|null $style
     */
    public function println($message, $verbosity, $style = null)
    {
        $this->print($message, $verbosity, $style);
        $this->print(PHP_EOL, $verbosity, $style);
    }

    /**
     * @param string $message
     * @param int   $verbosity
     * @param string|null $style
     */
    public function print($message, $verbosity, $style = null)
    {
        if ($verbosity > $this->verbosity) {
            return;
        }

        $message = sprintf(
            '%s%s%s',
            $this->supportColors ? $this->styles[$style] : '',
            $message,
            $this->supportColors ? $this->styles['reset'] : ''
        );

        echo $message;
    }

}
