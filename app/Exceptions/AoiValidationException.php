<?php

namespace App\Exceptions;

/**
 * Thrown by App\Services\Geo\AoiValidator when a drawn AOI polygon fails a
 * check (area cap, dimension cap, self-intersection). The message is written
 * to be shown to the researcher as-is — no silent failures.
 */
class AoiValidationException extends \RuntimeException
{
}
