<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a single (non-recurring) booking is attempted against a
 * clinician who is already occupied. Recurring series flag clashes instead.
 */
class ClinicClashException extends RuntimeException
{
}
