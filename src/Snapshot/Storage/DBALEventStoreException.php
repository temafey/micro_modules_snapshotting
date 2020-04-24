<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Storage;

use Broadway\EventStore\EventStoreException;
use Doctrine\DBAL\DBALException;

/**
 * Wraps exceptions thrown by the DBAL event store.
 */
class DBALEventStoreException extends EventStoreException
{
    /**
     * @param DBALException $exception
     *
     * @return DBALEventStoreException
     */
    public static function create(DBALException $exception): self
    {
        return new self($exception->getMessage(), 0, $exception);
    }
}
