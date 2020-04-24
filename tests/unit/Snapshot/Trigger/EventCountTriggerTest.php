<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Tests\Unit\Snapshot\Trigger;

use MicroModule\Snapshotting\EventSourcing\Testing\TestEventSourcedAggregateRoot;
use MicroModule\Snapshotting\Snapshot\Trigger\EventCountTrigger;
use PHPUnit\Framework\TestCase;
use stdClass;

class EventCountTriggerTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_snapshot_when_number_of_uncommitted_events_exceeds_given_event_count(): void
    {
        $trigger = new EventCountTrigger(1);

        $aggregateRoot = new TestEventSourcedAggregateRoot();
        $this->assertFalse($trigger->shouldSnapshot($aggregateRoot));

        $aggregateRoot->apply(new stdClass());
        $this->assertTrue($trigger->shouldSnapshot($aggregateRoot));

        $aggregateRoot->apply(new stdClass());
        $this->assertTrue($trigger->shouldSnapshot($aggregateRoot));
    }
}
