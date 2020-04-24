<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\EventSourcing;

use Broadway\Domain\DomainMessage;
use Broadway\EventSourcing\EventSourcedAggregateRoot;

/**
 * Interface AggregateFactoryInterface.
 */
interface AggregateFactoryInterface
{
    /**
     * @param string        $aggregateClass the FQCN of the Aggregate to create
     * @param DomainMessage $domainMessage
     *
     * @return EventSourcedAggregateRoot
     */
    public function create(string $aggregateClass, DomainMessage $domainMessage): EventSourcedAggregateRoot;
}
