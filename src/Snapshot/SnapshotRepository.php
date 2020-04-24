<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot;

use MicroModule\Snapshotting\EventSourcing\AggregateAssemblerInterface;
use MicroModule\Snapshotting\EventSourcing\AggregateFactoryInterface;
use MicroModule\Snapshotting\Snapshot\Storage\SnapshotNotFoundException;
use MicroModule\Snapshotting\Snapshot\Storage\SnapshotStoreInterface;
use Assert\Assertion as Assert;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Throwable;

class SnapshotRepository implements SnapshotRepositoryInterface
{
    /**
     * @var SnapshotStoreInterface
     */
    private $snapshotStore;

    /**
     * @var string
     */
    private $aggregateClass;

    /**
     * @var AggregateFactoryInterface
     */
    private $aggregateFactory;

    /**
     * @param SnapshotStoreInterface    $snapshotStore
     * @param string                    $aggregateClass
     * @param AggregateFactoryInterface $aggregateFactory
     */
    public function __construct(
        SnapshotStoreInterface $snapshotStore,
        string $aggregateClass,
        AggregateFactoryInterface $aggregateFactory
    ) {
        $this->assertExtendsEventSourcedAggregateRoot($aggregateClass);

        $this->snapshotStore = $snapshotStore;
        $this->aggregateClass = $aggregateClass;
        $this->aggregateFactory = $aggregateFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable
     */
    public function load($id): ?Snapshot
    {
        try {
            $domainMessage = $this->snapshotStore->load($id);
            $aggregateRoot = $this->aggregateFactory->create($this->aggregateClass, $domainMessage);

            return new Snapshot($aggregateRoot);
        } catch (SnapshotNotFoundException $e) {
            return null;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * @param string $id
     *
     * @return mixed|null
     *
     * @throws Throwable
     */
    public function getSnapshotPayload(string $id)
    {
        try {
            $domainMessage = $this->snapshotStore->load($id);

            return $domainMessage->getPayload();
        } catch (SnapshotNotFoundException $e) {
            return null;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(Snapshot $snapshot): void
    {
        $aggregate = $snapshot->getAggregateRoot();

        if (!$aggregate instanceof AggregateAssemblerInterface) {
            throw new RepositoryException('AggregateRoot not instance of AggregateAssemblerInterface');
        }
        // maybe we can get generics one day.... ;)
        Assert::isInstanceOf($aggregate, $this->aggregateClass);
        $this->snapshotStore->append($aggregate->getAggregateRootId(), DomainMessage::recordNow(
            $aggregate->getAggregateRootId(),
            $snapshot->getPlayhead(),
            new Metadata([]),
            $aggregate->assembleToValueObject()
        ));
    }

    /**
     * @param string $class
     */
    private function assertExtendsEventSourcedAggregateRoot(string $class): void
    {
        Assert::subclassOf(
            $class,
            EventSourcedAggregateRoot::class,
            sprintf("Class '%s' is not an EventSourcedAggregateRoot.", $class)
        );

        Assert::implementsInterface(
            $class,
            AggregateAssemblerInterface::class,
            sprintf("Class '%s' is not instanse of AggregateAssemblerInterface.", $class)
        );
    }
}
