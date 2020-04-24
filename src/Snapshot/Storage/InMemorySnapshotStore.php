<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Storage;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\Exception\InvalidIdentifierException;
use Broadway\Serializer\Serializer;
use Broadway\UuidGenerator\Converter\BinaryUuidConverterInterface;
use LogicException;

/**
 * In-memory implementation of an snapshot store.
 *
 * Useful for testing code that uses an snapshot store.
 */
final class InMemorySnapshotStore implements SnapshotStoreInterface
{
    /**
     * Snapshots in memory storage.
     *
     * @var mixed[]
     */
    private $snapshots = [];

    /**
     * @var Serializer
     */
    private $payloadSerializer;

    /**
     * @var Serializer
     */
    private $metadataSerializer;

    /**
     * @var bool
     */
    private $useBinary;

    /**
     * @var null|BinaryUuidConverterInterface
     */
    private $binaryUuidConverter;

    /**
     * DBALSnapshotStore constructor.
     *
     * @param Serializer                        $payloadSerializer
     * @param Serializer                        $metadataSerializer
     * @param bool                              $useBinary
     * @param BinaryUuidConverterInterface|null $binaryUuidConverter
     */
    public function __construct(
        Serializer $payloadSerializer,
        Serializer $metadataSerializer,
        bool $useBinary,
        ?BinaryUuidConverterInterface $binaryUuidConverter = null
    ) {
        $this->payloadSerializer = $payloadSerializer;
        $this->metadataSerializer = $metadataSerializer;
        $this->useBinary = $useBinary;
        $this->binaryUuidConverter = $binaryUuidConverter;

        if ($this->useBinary && null === $binaryUuidConverter) {
            throw new LogicException('binary UUID converter is required when using binary');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load($uuid, int $offset = 0): DomainMessage
    {
        $id = (string) $uuid;

        if (isset($this->snapshots[$id]) && isset($this->snapshots[$id][$offset])) {
            return $this->deserializeDomainMessage($this->snapshots[$id][$offset]);
        }

        throw new SnapshotNotFoundException(sprintf('Snapshot not found for aggregate with id %s', $id));
    }

    /**
     * {@inheritdoc}
     */
    public function append($uuid, DomainMessage $domainMessage): void
    {
        $data = [
            'uuid' => (string) $this->convertIdentifierToStorageValue($domainMessage->getId()),
            'playhead' => $domainMessage->getPlayhead(),
            'metadata' => json_encode($this->metadataSerializer->serialize($domainMessage->getMetadata())),
            'payload' => json_encode($this->payloadSerializer->serialize($domainMessage->getPayload())),
            'recorded_on' => $domainMessage->getRecordedOn()->toString(),
            'type' => $domainMessage->getType(),
        ];

        if (!isset($this->snapshots[$data['uuid']])) {
            $this->snapshots[$data['uuid']] = [];
        }

        array_unshift($this->snapshots[$data['uuid']], $data);
    }

    /**
     * @param mixed[] $row
     *
     * @return DomainMessage
     */
    private function deserializeDomainMessage(array $row): DomainMessage
    {
        return new DomainMessage(
            $this->convertStorageValueToIdentifier($row['uuid']),
            (int) $row['playhead'],
            $this->metadataSerializer->deserialize(json_decode($row['metadata'], true)),
            $this->payloadSerializer->deserialize(json_decode($row['payload'], true)),
            DateTime::fromString($row['recorded_on'])
        );
    }

    /**
     * @param mixed $uuid
     *
     * @return mixed
     */
    private function convertIdentifierToStorageValue($uuid)
    {
        if ($this->useBinary && $this->binaryUuidConverter) {
            try {
                return $this->binaryUuidConverter->fromString($uuid);
            } catch (\Throwable $e) {
                throw new InvalidIdentifierException(
                    'Only valid UUIDs are allowed to by used with the binary storage mode.'
                );
            }
        }

        return $uuid;
    }

    /**
     * @param mixed $uuid
     *
     * @return mixed
     */
    private function convertStorageValueToIdentifier($uuid)
    {
        if ($this->useBinary && $this->binaryUuidConverter) {
            try {
                return $this->binaryUuidConverter->fromBytes($uuid);
            } catch (\Throwable $e) {
                throw new InvalidIdentifierException(
                    'Could not convert binary storage value to UUID.'
                );
            }
        }

        return $uuid;
    }
}
