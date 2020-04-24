<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\EventSourcing;

use MicroModule\ValueObject\ValueObjectInterface;

/**
 * Interface AggregateAssemblerInterface.
 */
interface AggregateAssemblerInterface
{
    /**
     * Assemble entity from value object.
     *
     * @param ValueObjectInterface $valueObject
     */
    public function assembleFromValueObject(ValueObjectInterface $valueObject): void;

    /**
     * Assemble value object from entity.
     *
     * @return ValueObjectInterface
     */
    public function assembleToValueObject(): ValueObjectInterface;
}
