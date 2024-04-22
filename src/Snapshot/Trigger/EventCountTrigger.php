<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Trigger;

use MicroModule\Snapshotting\Snapshot\ShouldNotSnapshot;
use MicroModule\Snapshotting\Snapshot\TriggerInterface;
use Broadway\EventSourcing\EventSourcedAggregateRoot;

class EventCountTrigger implements TriggerInterface
{
    /**
     * @var int
     */
    private $eventCount;

    /**
     * @param int $eventCount
     */
    public function __construct(int $eventCount = 20)
    {
        $this->eventCount = $eventCount;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldSnapshot(EventSourcedAggregateRoot $aggregateRoot)
    {
        $clonedAggregateRoot = clone $aggregateRoot;

        foreach ($clonedAggregateRoot->getUncommittedEvents() as $domainMessage) {
            if ($domainMessage->getPayload() instanceof ShouldNotSnapshot) {
                continue;
            }
            if (0 === ($domainMessage->getPlayhead() + 1) % $this->eventCount) {
                return true;
            }
        }

        return false;
    }
}
