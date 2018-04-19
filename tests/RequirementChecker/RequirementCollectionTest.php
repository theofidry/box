<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use function iterator_to_array;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\RequirementChecker\RequirementCollection
 */
class RequirementCollectionTest extends TestCase
{
    public function test_it_is_empty_by_default()
    {
        $requirements = new RequirementCollection();

        $this->assertSame([], iterator_to_array($requirements));
        $this->assertSame([], $requirements->getRequirements());
        $this->assertCount(tests/StubGeneratorTest.php0, $requirements);
        $this->assertTrue($requirements->evaluateRequirements());
    }

    public function test_it_can_have_and_evaluate_requirements()
    {
        $requirements = new RequirementCollection();

        $reqs = [
            $requirementA = new Requirement(
                'return true;',
                'req tA',
                'req hA'
            ),
            $requirementB = new Requirement(
                'return true;',
                'req tB',
                'req hB'
            ),
        ];

        foreach ($reqs as $requirement) {
            $requirements->add($requirement);
        }

        $this->assertSame($reqs, iterator_to_array($requirements));
        $this->assertSame($reqs, $requirements->getRequirements());
        $this->assertCount(2, $requirements);
        $this->assertTrue($requirements->evaluateRequirements());

        $requirements->addRequirement(
            'return false;',
            'req tC',
            'req hC'
        );

        $this->assertCount(3, $requirements);
        $this->assertFalse($requirements->evaluateRequirements());

        $retrievedRequirements = $requirements->getRequirements();

        $this->assertSame($retrievedRequirements, iterator_to_array($requirements));

        $this->assertSame($requirementA, $retrievedRequirements[0]);
        $this->assertSame($requirementB, $retrievedRequirements[1]);

        $requirementC = $retrievedRequirements[2];

        $this->assertSame('return false;', $requirementC->getIsFullfilledChecker());
        $this->assertTrue($requirement->isFulfilled());
        $this->assertSame('req tC', $requirementC->getTestMessage());
        $this->assertSame('req hC', $requirementC->getHelpText());
    }
}
