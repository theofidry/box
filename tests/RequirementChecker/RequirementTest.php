<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;


use Error;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\RequirementChecker\Requirement
 */
class RequirementTest extends TestCase
{
    public function test_it_can_be_created()
    {
        $requirement = new Requirement(
            'return true;',
            'Test message',
            'Help message'
        );

        $this->assertSame('return true;', $requirement->getIsFullfilledChecker());
        $this->assertTrue($requirement->isFulfilled());
        $this->assertSame('Test message', $requirement->getTestMessage());
        $this->assertSame('Help message', $requirement->getHelpText());
    }

    public function test_it_evaluates_the_check_lazily()
    {
        $requirement = new Requirement(
            'throw new \Error();',
            'Test message',
            'Help message'
        );

        $this->assertSame('throw new \Error();', $requirement->getIsFullfilledChecker());

        try {
            $requirement->isFulfilled();

            $this->fail('Expected exception to be thrown.');
        } catch (Error $error) {
            $this->assertTrue(true);
        }
    }

    public function test_it_casts_the_fulfilled_result_into_a_boolean()
    {
        $requirement = new Requirement(
            'return 1;',
            '',
            ''
        );

        $this->assertTrue($requirement->isFulfilled());

        $requirement = new Requirement(
            'return 0;',
            '',
            ''
        );

        $this->assertFalse($requirement->isFulfilled());

        $requirement = new Requirement(
            'return new \stdClass();',
            '',
            ''
        );

        $this->assertTrue($requirement->isFulfilled());
    }

    public function test_it_evaluates_the_check_only_once()
    {
        $GLOBALS['x'] = -1;

        $requirement = new Requirement(
            <<<'PHP'
$GLOBALS['x']++;

return $GLOBALS['x'];
PHP
            ,
            'Test message',
            'Help message'
        );

        $this->assertFalse($requirement->isFulfilled());
        $this->assertFalse($requirement->isFulfilled());    // Would have gave `true` if it was evaluated a second time

        unset($GLOBALS['x']);
    }
}
