<?php

namespace App\Exceptions;

/**
 * Thrown by App\Services\ClassificationRunner when it detects mid-run that
 * the ClassificationRun was cancelled and kills the Python subprocess.
 * Deliberately separate from a generic \Throwable so RunClassificationJob
 * can tell "the user cancelled this" apart from "this genuinely failed" --
 * the former shouldn't be logged as an error or treated as a failure.
 */
class ClassificationCancelledException extends \RuntimeException
{
}
