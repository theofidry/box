<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box;

use ErrorException;
use function error_reporting;
use function set_error_handler;

($GLOBALS['_BOX_BOOTSTRAP'] = static function (): void {
    register_aliases();
})();

// Convert errors to exceptions
set_error_handler(
    static function (int $code, string $message, string $file = '', int $line = -1): void {
        if (error_reporting() & $code) {
            throw new ErrorException($message, 0, $code, (string) $file, $line);
        }
    }
);
