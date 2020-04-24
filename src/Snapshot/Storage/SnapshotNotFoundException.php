<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Storage;

use RuntimeException;

/**
 * Exception thrown if an event stream is not found.
 */
final class SnapshotNotFoundException extends RuntimeException
{
}
