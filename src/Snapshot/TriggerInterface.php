<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot;

use Broadway\EventSourcing\EventSourcedAggregateRoot;

interface TriggerInterface
{
    /**
     * @param EventSourcedAggregateRoot $aggregateRoot
     *
     * @return mixed
     */
    public function shouldSnapshot(EventSourcedAggregateRoot $aggregateRoot);
}
