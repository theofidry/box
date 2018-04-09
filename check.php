<?php

declare(strict_types=1);

use function KevinGH\Box\RequirementChecker\check_requirements;

require 'vendor/autoload.php';
require 'src/RequirementChecker/check.php';

$checkPassed = check_requirements(__DIR__.'/composer.json', true, false);

if (false === $checkPassed) {
    exit(1);
}