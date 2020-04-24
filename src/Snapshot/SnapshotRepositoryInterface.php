<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot;

interface SnapshotRepositoryInterface
{
    /**
     * @param mixed $id should be unique across aggregate types
     *
     * @return Snapshot|null
     */
    public function load($id): ?Snapshot;

    /**
     * @param Snapshot $snapshot
     */
    public function save(Snapshot $snapshot): void;
}
