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

use Symfony\Requirements\Requirement as SymfonyRequirement;

final class LazyRequirement extends SymfonyRequirement
{
    private $checkIsFulfilled;
    private $isFulfilled;

    /**
     * {@inheritdoc}
     *
     * @param string $checkIsFulfilled Callable as a string (it will be evaluated with `eval()` returning a `bool` value telling whether the
     *                                 requirement is fulfilled or not. The condition is evaluated lazily.
     */
    public function __construct($checkIsFulfilled, $testMessage, $helpHtml, $helpText = null, $optional = false)
    {
        parent::__construct(false, $testMessage, $helpHtml, $helpText, $optional);

        $this->checkIsFulfilled =$checkIsFulfilled;
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        if (null === $this->isFulfilled) {
            $this->isFulfilled = eval($this->checkIsFulfilled);
        }

        return $this->isFulfilled;
    }

    /**
     * @return string
     */
    public function getIsFullfilledChecker()
    {
        return $this->checkIsFulfilled;
    }
}