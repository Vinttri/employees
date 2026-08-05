<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

/**
 * Re-runs the idempotent legacy audit import for installations where the
 * cross-app table was hidden by the migration schema wrapper in version 2037.
 */
class Version2038Date20260805190000 extends Version2037Date20260805180000 {
}
