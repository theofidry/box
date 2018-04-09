<?php

declare(strict_types=1);

use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\RequirementChecker\check_requirements;
use KevinGH\Box\RequirementChecker\RequirementsDumper;

require 'vendor/autoload.php';

$checker = RequirementsDumper::dumpChecker(__DIR__.'/composer.json');

dump_file('check.php', $checker, true);
