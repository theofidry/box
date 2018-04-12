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

use Symfony\Requirements\RequirementCollection as SymfonyRequirementCollection;

final class LazyRequirementCollection extends SymfonyRequirementCollection
{
    /**
     * Adds a mandatory requirement evaluated lazily.
     *
     * @param string      $checkIsFulfilled Whether the requirement is fulfilled; This string is will be evaluated with `eval()` because
     *                                      PHP does not support the serialization or the export of closures.
     * @param string      $testMessage      The message for testing the requirement
     * @param string      $helpHtml         The help text formatted in HTML for resolving the problem
     * @param string|null $helpText         The help text (when null, it will be inferred from $helpHtml, i.e. stripped from HTML tags)
     */
    public function addLazyRequirement($checkIsFulfilled, $testMessage, $helpHtml, $helpText = null)
    {
        $this->add(new LazyRequirement($checkIsFulfilled, $testMessage, $helpHtml, $helpText, false));
    }
}