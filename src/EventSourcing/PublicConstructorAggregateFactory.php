<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\EventSourcing;

use Broadway\Domain\DomainMessage;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Closure;

/**
 * Creates aggregates by instantiating the aggregateClass.
 */
final class PublicConstructorAggregateFactory implements AggregateFactoryInterface
{
    /**
     * @param string        $aggregateClass the FQCN of the Aggregate to create
     * @param DomainMessage $domainMessage
     *
     * @return EventSourcedAggregateRoot
     *
     * @throws AggregateFactoryException
     */
    public function create(string $aggregateClass, DomainMessage $domainMessage): EventSourcedAggregateRoot
    {
        $aggregate = new $aggregateClass();

        if (!$aggregate instanceof EventSourcedAggregateRoot) {
            throw new AggregateFactoryException('AggregateClass not instance of EventSourcedAggregateRoot');
        }

        if (!$aggregate instanceof AggregateAssemblerInterface) {
            throw new AggregateFactoryException('AggregateRoot not instance of AggregateAssemblerInterface');
        }
        $aggregate->assembleFromValueObject($domainMessage->getPayload());
        $this->setPrivate($aggregate, 'playhead')($domainMessage->getPlayhead());

        return $aggregate;
    }

    /**
     * Return closure ,that can set any private or protected property value in any object.
     *
     * @param object $obj
     * @param string $attribute
     *
     * @return Closure
     */
    private function setPrivate(object $obj, string $attribute): Closure
    {
        $setter = function ($value) use ($attribute): void {
            $this->$attribute = $value;
        };

        return Closure::bind($setter, $obj, EventSourcedAggregateRoot::class);
    }
}
