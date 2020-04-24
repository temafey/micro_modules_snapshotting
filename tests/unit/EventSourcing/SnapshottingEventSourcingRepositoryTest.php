<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Tests\Unit\EventSourcing;

use MicroModule\Snapshotting\EventSourcing\SnapshottingEventSourcingRepository;
use MicroModule\Snapshotting\EventSourcing\Testing\TestEventSourcedAggregateRoot;
use MicroModule\Snapshotting\Snapshot\Snapshot;
use MicroModule\Snapshotting\Snapshot\SnapshotRepository;
use MicroModule\Snapshotting\Snapshot\Trigger\EventCountTrigger;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\EventSourcing\EventSourcingRepository;
use Broadway\EventStore\EventStore;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use stdClass;

/**
 * Class SnapshottingEventSourcingRepositoryTest.
 */
class SnapshottingEventSourcingRepositoryTest extends TestCase
{
    /**
     * @var ObjectProphecy
     */
    private $eventStore;

    /**
     * @var ObjectProphecy
     */
    private $eventSourcingRepository;

    /**
     * @var ObjectProphecy
     */
    private $snapshotRepository;

    /**
     * @var SnapshottingEventSourcingRepository
     */
    private $snapshottingEventSourcingRepository;

    public function setUp(): void
    {
        $this->eventSourcingRepository = $this->prophesize(EventSourcingRepository::class);
        $this->eventStore = $this->prophesize(EventStore::class);
        $this->snapshotRepository = $this->prophesize(SnapshotRepository::class);

        $this->snapshottingEventSourcingRepository = new SnapshottingEventSourcingRepository(
            $this->eventSourcingRepository->reveal(),
            $this->eventStore->reveal(),
            $this->snapshotRepository->reveal(),
            new EventCountTrigger(100)
        );
    }

    /**
     * @test
     */
    public function it_reconstitutes_aggregate_when_no_snapshot_found(): void
    {
        $this->snapshotRepository
            ->load(42)
            ->shouldBeCalled()
            ->willReturn(null)
        ;

        $this->eventSourcingRepository
            ->load(42)
            ->shouldBeCalled()
            ->willReturn($this->prophesize(EventSourcedAggregateRoot::class)->reveal())
        ;

        $this->snapshottingEventSourcingRepository->load(42);
    }

    /**
     * @test
     */
    public function it_queries_the_event_store_for_events_recorded_after_playhead_of_snapshot(): void
    {
        $this->markTestIncomplete('Refactor this test');
        // create a snapshot of an aggregate root with 5 committed events
        $snapshot = $this->createSnapshot();
        $this->assertEquals(4, $snapshot->getPlayhead());

        $this->snapshotRepository
            ->load(42)
            ->shouldBeCalled()
            ->willReturn($snapshot)
        ;

        $this->eventSourcingRepository
            ->load(42)
            ->shouldNotBeCalled()
        ;

        // 2 new events since last snapshot
        $domainEventStream = new DomainEventStream([
            DomainMessage::recordNow(42, 5, new Metadata([]), new stdClass()),
            DomainMessage::recordNow(42, 6, new Metadata([]), new stdClass()),
        ]);

        $this->eventStore
            ->loadFromPlayhead(42, 5)
            ->shouldBeCalled()
            ->willReturn($domainEventStream)
        ;

        $aggregateRoot = $this->snapshottingEventSourcingRepository->load(42);
        $this->assertEquals(6, $aggregateRoot->getPlayhead());
    }

    /**
     * @test
     */
    public function it_does_not_take_a_snapshot_when_threshold_not_reached(): void
    {
        $aggregateRoot = $this->createAggregateRootWithEvents(99);

        $this->snapshotRepository
            ->save(Argument::type(Snapshot::class))
            ->shouldNotBeCalled()
        ;

        $this->snapshottingEventSourcingRepository->save($aggregateRoot);
    }

    /**
     * @test
     */
    public function it_takes_a_snapshot_when_threshold_reached(): void
    {
        $aggregateRoot = $this->createAggregateRootWithEvents(100);

        $this->snapshotRepository
            ->save(Argument::type(Snapshot::class))
            ->shouldBeCalled()
        ;

        $this->snapshottingEventSourcingRepository->save($aggregateRoot);
    }

    /**
     * @return Snapshot
     */
    private function createSnapshot(): Snapshot
    {
        $aggregateRoot = $this->createAggregateRootWithEvents(5);
        $aggregateRoot->getUncommittedEvents();

        return new Snapshot($aggregateRoot);
    }

    /**
     * @param int $countEvents
     *
     * @return TestEventSourcedAggregateRoot
     */
    private function createAggregateRootWithEvents(int $countEvents): TestEventSourcedAggregateRoot
    {
        $aggregateRoot = new TestEventSourcedAggregateRoot();

        for ($i = 0; $i < $countEvents; ++$i) {
            $aggregateRoot->apply(new stdClass());
        }

        return $aggregateRoot;
    }
}
