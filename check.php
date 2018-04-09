<?php

declare(strict_types=1);

use function KevinGH\Box\Requirement\check_requirements;

require 'vendor/autoload.php';
require 'src/Requirement/check.php';

$checkPassed = check_requirements(__DIR__.'/composer.json', true);

if (false === $checkPassed) {
    exit(1);
}