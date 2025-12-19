<?php

declare(strict_types=1);

namespace MicroModule\Snapshotting\Snapshot\Storage;

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\Exception\InvalidIdentifierException;
use Broadway\Serializer\Serializer;
use Broadway\UuidGenerator\Converter\BinaryUuidConverterInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use LogicException;
use Throwable;

/**
 * Class DBALSnapshotStore.
 *
 * @SuppressWarnings(PHPMD)
 */
class DBALSnapshotStore implements SnapshotStoreInterface
{
    /**
     * Database connection object.
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * Saga state database table name.
     *
     * @var string
     */
    protected string $tableName;

    /**
     * @var Serializer
     */
    protected Serializer $payloadSerializer;

    /**
     * @var Serializer
     */
    protected Serializer $metadataSerializer;

    /**
     * @var Statement
     */
    protected ?Statement $loadStatement = null;

    /**
     * @var bool
     */
    protected bool $useBinary;

    /**
     * @var null|BinaryUuidConverterInterface
     */
    protected ?BinaryUuidConverterInterface $binaryUuidConverter = null;

    /**
     * DBALSnapshotStore constructor.
     *
     * @param Connection                        $connection
     * @param Serializer                        $payloadSerializer
     * @param Serializer                        $metadataSerializer
     * @param string                            $tableName
     * @param bool                              $useBinary
     * @param BinaryUuidConverterInterface|null $binaryUuidConverter
     */
    public function __construct(
        Connection $connection,
        Serializer $payloadSerializer,
        Serializer $metadataSerializer,
        string $tableName,
        bool $useBinary,
        ?BinaryUuidConverterInterface $binaryUuidConverter = null
    ) {
        $this->connection = $connection;
        $this->payloadSerializer = $payloadSerializer;
        $this->metadataSerializer = $metadataSerializer;
        $this->tableName = $tableName;
        $this->useBinary = $useBinary;
        $this->binaryUuidConverter = $binaryUuidConverter;

        if ($this->useBinary && null === $binaryUuidConverter) {
            throw new LogicException('binary UUID converter is required when using binary');
        }
    }

    /**
     * Load snapshot by unique id and with offset if needed.
     *
     * @param mixed $uuid   should be unique across aggregate types
     * @param int   $offset
     *
     * @return DomainMessage
     *
     * @throws DBALException
     */
    public function load($uuid, int $offset = 0): DomainMessage
    {
        $row = $this->fetch($uuid, $offset);

        if (false === $row) {
            throw new SnapshotNotFoundException(sprintf('Snapshot not found for aggregate with id %s for table %s', $uuid, $this->tableName));
        }

        return $this->deserializeDomainMessage($row);
    }

    /**
     * Fetch latest snapshots by id, offset and limit.
     *
     * @param mixed $uuid
     * @param int   $offset
     * @param int   $count
     *
     * @return mixed
     *
     * @throws DBALException
     */
    protected function fetch($uuid, int $offset = 0, int $count = 1)
    {
        $statement = $this->prepareLoadStatement();
        $statement->bindValue(1, (string) $this->convertIdentifierToStorageValue($uuid));
        $statement->bindValue(2, $count, ParameterType::INTEGER);
        $statement->bindValue(3, $offset, ParameterType::INTEGER);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * Append new snapshot payload into storage.
     *
     * @param mixed         $uuid
     * @param DomainMessage $domainMessage
     *
     * @throws ConnectionException
     */
    public function append($uuid, DomainMessage $domainMessage): void
    {
        $this->connection->beginTransaction();

        try {
            $this->insertMessage($this->connection, $domainMessage);

            $this->connection->commit();
        } catch (DBALException $exception) {
            $this->connection->rollBack();

            throw DBALEventStoreException::create($exception);
        }
    }

    /**
     * Insert new snapshot to store.
     *
     * @param Connection    $connection
     * @param DomainMessage $domainMessage
     *
     * @throws DBALException
     */
    protected function insertMessage(Connection $connection, DomainMessage $domainMessage): void
    {
        $data = [
            'uuid' => (string) $this->convertIdentifierToStorageValue($domainMessage->getId()),
            'playhead' => $domainMessage->getPlayhead(),
            'metadata' => json_encode($this->metadataSerializer->serialize($domainMessage->getMetadata())),
            'payload' => json_encode($this->payloadSerializer->serialize($domainMessage->getPayload())),
            'recorded_on' => $domainMessage->getRecordedOn()->toString(),
            'type' => $domainMessage->getType(),
        ];

        $connection->insert($this->tableName, $data);
    }

    /**
     * @param Schema $schema
     *
     * @return Table|null
     */
    public function configureSchema(Schema $schema): ?Table
    {
        if ($schema->hasTable($this->tableName)) {
            return null;
        }

        return $this->configureTable($schema);
    }

    /**
     * @param Schema|null $schema
     *
     * @return Table
     */
    public function configureTable(?Schema $schema = null): Table
    {
        $schema = $schema ?: new Schema();
        $uuidColumnDefinition = [
            'type' => 'guid',
            'params' => [
                'length' => 36,
            ],
        ];

        if ($this->useBinary) {
            $uuidColumnDefinition['type'] = 'binary';
            $uuidColumnDefinition['params'] = [
                'length' => 16,
                'fixed' => true,
            ];
        }
        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('uuid', $uuidColumnDefinition['type'], $uuidColumnDefinition['params']);
        $table->addColumn('playhead', 'integer', ['unsigned' => true]);
        $table->addColumn('payload', 'text');
        $table->addColumn('metadata', 'text');
        $table->addColumn('recorded_on', 'string', ['length' => 32]);
        $table->addColumn('type', 'string', ['length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid', 'playhead']);

        return $table;
    }

    /**
     * Prepare query.
     *
     * @return Statement
     *
     * @throws DBALException
     */
    protected function prepareLoadStatement(): Statement
    {
        if (null === $this->loadStatement) {
            $query = '
                SELECT uuid, playhead, metadata, payload, recorded_on 
                FROM ' . $this->tableName . ' 
                WHERE uuid = ? 
                ORDER BY playhead DESC 
                LIMIT ? 
                OFFSET ?;
            ';
            $this->loadStatement = $this->connection->prepare($query);
        }

        return $this->loadStatement;
    }

    /**
     * @param mixed[] $row
     *
     * @return DomainMessage
     */
    protected function deserializeDomainMessage(array $row): DomainMessage
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
    protected function convertIdentifierToStorageValue($uuid)
    {
        if ($this->useBinary && $this->binaryUuidConverter) {
            try {
                return $this->binaryUuidConverter->fromString($uuid);
            } catch (Throwable $e) {
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
    protected function convertStorageValueToIdentifier($uuid)
    {
        if ($this->useBinary && $this->binaryUuidConverter) {
            try {
                return $this->binaryUuidConverter->fromBytes($uuid);
            } catch (Throwable $e) {
                throw new InvalidIdentifierException(
                    'Could not convert binary storage value to UUID.'
                );
            }
        }

        return $uuid;
    }
}
