<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\EventSourcing;

use MicroModule\Snapshotting\Snapshot\Snapshot;
use MicroModule\Snapshotting\Snapshot\SnapshotRepositoryInterface;
use MicroModule\Snapshotting\Snapshot\TriggerInterface;
use Broadway\Domain\AggregateRoot;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\EventSourcing\EventSourcingRepository;
use Broadway\EventStore\EventStore;
use Broadway\Repository\Repository;

class SnapshottingEventSourcingRepository implements Repository
{
    /**
     * @var EventSourcingRepository
     */
    private $eventSourcingRepository;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var SnapshotRepositoryInterface
     */
    private $snapshotRepository;

    /**
     * @var TriggerInterface
     */
    private $trigger;

    /**
     * SnapshottingEventSourcingRepository constructor.
     *
     * @param EventSourcingRepository     $eventSourcingRepository
     * @param EventStore                  $eventStore
     * @param SnapshotRepositoryInterface $snapshotRepository
     * @param TriggerInterface            $trigger
     */
    public function __construct(
        EventSourcingRepository $eventSourcingRepository,
        EventStore $eventStore,
        SnapshotRepositoryInterface $snapshotRepository,
        TriggerInterface $trigger
    ) {
        $this->eventSourcingRepository = $eventSourcingRepository;
        $this->eventStore = $eventStore;
        $this->snapshotRepository = $snapshotRepository;
        $this->trigger = $trigger;
    }

    /**
     * {@inheritdoc}
     */
    public function load($id): AggregateRoot
    {
        $snapshot = $this->snapshotRepository->load($id);

        if (null === $snapshot) {
            return $this->eventSourcingRepository->load($id);
        }
        $aggregateRoot = $snapshot->getAggregateRoot();
        $aggregateRoot->initializeState(
            $this->eventStore->loadFromPlayhead($id, $snapshot->getPlayhead())
        );

        return $aggregateRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function save(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof EventSourcedAggregateRoot) {
            throw new SnapshottingEventSourcingRepositoryException('Aggregate not instance of EventSourcedAggregateRoot');
        }

        if ($this->trigger->shouldSnapshot($aggregate)) {
            $this->snapshotRepository->save(
                new Snapshot($aggregate)
            );
        }
        $this->eventSourcingRepository->save($aggregate);
    }
}
