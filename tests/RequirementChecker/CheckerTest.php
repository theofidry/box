<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\Console\DisplayNormalizer;
use function ob_get_contents;
use PHPUnit\Framework\TestCase;
use function preg_replace;

/**
 * @covers \KevinGH\Box\RequirementChecker\Checker
 */
class CheckerTest extends TestCase
{
    /**
     * @dataProvider provideRequirements
     */
    public function test_it_can_check_requirements(
        RequirementCollection $requirements,
        int $verbosity,
        bool $expectedResult,
        string $expectedOutput
    ) {
        $actualResult = $requirements->evaluateRequirements();

        ob_start();
        Checker::printCheck(
            new Printer(
                $verbosity,
                false
            ),
            $requirements
        );

        $actualOutput = ob_get_contents();
        ob_end_clean();

        $actualOutput = DisplayNormalizer::removeTrailingSpaces($actualOutput);
        $actualOutput = preg_replace(
            '~/.+/php.ini~',
            '/path/to/php.ini',
            $actualOutput
        );

        $this->assertSame($expectedOutput, $actualOutput);
        $this->assertSame($expectedResult, $actualResult);
    }

    public function provideRequirements()
    {
        yield (function () {
            return [
                new RequirementCollection(),
                InputOutput::VERBOSITY_DEBUG,
                true,
                <<<'EOF'

Box Requirements Checker
========================

> PHP is using the following php.ini file:
  /path/to/php.ini

> No requirements found.


 [OK] Your system is ready to run the application.


EOF
            ];
        })();

        yield (function () {
            return [
                new RequirementCollection(),
                InputOutput::VERBOSITY_VERY_VERBOSE,
                true,
                <<<'EOF'

Box Requirements Checker
========================

> PHP is using the following php.ini file:
  /path/to/php.ini

> No requirements found.


 [OK] Your system is ready to run the application.


EOF
            ];
        })();

        foreach ([InputOutput::VERBOSITY_VERBOSE, InputOutput::VERBOSITY_NORMAL, InputOutput::VERBOSITY_QUIET] as $verbosity) {
            yield (function () use ($verbosity) {
                return [
                    new RequirementCollection(),
                    $verbosity,
                    true,
                    ''
                ];
            })();
        }

        yield (function () {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                'return true;',
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                'return true;',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                InputOutput::VERBOSITY_DEBUG,
                true,
                <<<'EOF'

Box Requirements Checker
========================

> PHP is using the following php.ini file:
  /path/to/php.ini

> Checking Box requirements:
  ✔ The application requires the version "7.2.0" or greater.
  ✔ The package "acme/foo" requires the extension "random".


 [OK] Your system is ready to run the application.


EOF
            ];
        })();

        yield (function () {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                'return true;',
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                'return true;',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                InputOutput::VERBOSITY_VERY_VERBOSE,
                true,
                <<<'EOF'

Box Requirements Checker
========================

> PHP is using the following php.ini file:
  /path/to/php.ini

> Checking Box requirements:
  ..


 [OK] Your system is ready to run the application.


EOF
            ];
        })();

        foreach ([InputOutput::VERBOSITY_VERBOSE, InputOutput::VERBOSITY_NORMAL, InputOutput::VERBOSITY_QUIET] as $verbosity) {
            yield (function () use ($verbosity) {
                $requirements = new RequirementCollection();

                $requirements->addRequirement(
                    'return true;',
                    'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                    'The application requires the version "7.2.0" or greater.'
                );
                $requirements->addRequirement(
                    'return true;',
                    'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                    'The package "acme/foo" requires the extension "random".'
                );

                return [
                    $requirements,
                    $verbosity,
                    true,
                    '',
                ];
            })();
        }
    }
}
