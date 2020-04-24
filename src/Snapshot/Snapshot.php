<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot;

use Broadway\EventSourcing\EventSourcedAggregateRoot;

class Snapshot
{
    /**
     * @var int
     */
    private $playhead;

    /**
     * @var EventSourcedAggregateRoot
     */
    private $aggregateRoot;

    /**
     * @param EventSourcedAggregateRoot $aggregateRoot
     */
    public function __construct(EventSourcedAggregateRoot $aggregateRoot)
    {
        $this->aggregateRoot = $aggregateRoot;
        $this->playhead = $aggregateRoot->getPlayhead();
    }

    /**
     * @return int
     */
    public function getPlayhead(): int
    {
        return $this->playhead;
    }

    /**
     * @return EventSourcedAggregateRoot
     */
    public function getAggregateRoot(): EventSourcedAggregateRoot
    {
        return $this->aggregateRoot;
    }
}
