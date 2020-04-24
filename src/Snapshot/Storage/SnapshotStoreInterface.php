<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Storage;

use Broadway\Domain\DomainMessage;

/**
 * Loads and stores snapshots.
 */
interface SnapshotStoreInterface
{
    /**
     * Load snapshot by unique id and with offset if needed.
     *
     * @param mixed $uuid   should be unique across aggregate types
     * @param int   $offset
     *
     * @return DomainMessage
     */
    public function load($uuid, int $offset = 0): DomainMessage;

    /**
     * Append new snapshot payload into storage.
     *
     * @param mixed         $uuid
     * @param DomainMessage $domainMessage
     */
    public function append($uuid, DomainMessage $domainMessage): void;
}
