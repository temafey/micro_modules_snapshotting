<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot;

use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\Snapshot\SnapshotInterface;

class Snapshot implements SnapshotInterface
{
    /** Playhead counter value */
    protected int $playhead;

    /**
     * EventSourcedAggregateRoot object
     */
    protected EventSourcedAggregateRoot $aggregateRoot;

    public function __construct(int $playhead, EventSourcedAggregateRoot $aggregateRoot)
    {
        $this->playhead = $playhead;
        $this->aggregateRoot = $aggregateRoot;
    }

    /**
     * Returns playhead value
     */
    public function getPlayhead(): int
    {
        return $this->playhead;
    }

    /**
     * Returns EventSourcedAggregateRoot object
     */
    public function getAggregateRoot(): EventSourcedAggregateRoot
    {
        return $this->aggregateRoot;
    }
}
