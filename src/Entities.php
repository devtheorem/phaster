<?php

namespace DevTheorem\Phaster;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\PeachySQL\QueryBuilder\SqlParams;
use Teapot\{HttpException, StatusCode};

abstract class Entities
{
    protected PeachySql $db;
    public string $idField = 'id';
    private string $idColumn;
    /** @var array<string, Prop> */
    private array $fullPropMap;
    /** @var array<string, mixed> */
    private array $map;
    private bool $validatesEntities;
    /** @var array<string, mixed> */
    private array $selectableMap = [];

    public function __construct(PeachySql $db)
    {
        $this->db = $db;
        $bcProps = Helpers::selectMapToPropMap($this->getSelectMap());
        $selectProps = $this->getSelectProps();

        foreach ($selectProps as $prop) {
            if (isset($bcProps[$prop->name])) {
                unset($bcProps[$prop->name]);
            }
        }

        $propMap = Helpers::propListToPropMap([...array_values($bcProps), ...$selectProps]);

        if (!isset($propMap[$this->idField])) {
            throw new \Exception("Missing required {$this->idField} property in select map");
        }

        $idParts = explode('.', $propMap[$this->idField]->col);
        $this->idColumn = $idParts[array_key_last($idParts)];
        $this->fullPropMap = $propMap;
        $this->map = $this->getMap();
        $writableProps = Helpers::selectMapToPropMap($this->map);

        foreach (array_keys($writableProps) as $field) {
            if (isset($propMap[$field]) && !$propMap[$field]->output) {
                throw new \Exception("Writable {$field} property cannot have output: false");
            }
        }

        $this->validatesEntities = (new \ReflectionMethod($this, 'validateEntity'))->class !== self::class;

        if ($this->validatesEntities) {
            // the existing entities are selected when updating, so the ID must be output
            if (!$propMap[$this->idField]->output) {
                throw new \Exception("{$this->idField} property must be output to validate entities");
            }

            // writable properties which aren't selectable won't be in the existing entities
            $this->selectableMap = Helpers::propMapToSelectMap(array_intersect_key($writableProps, $propMap));
        }
    }

    /**
     * Returns the name of the table or view to select/insert/update/delete from
     */
    protected function getTableName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Returns a map of properties to writable columns for the current table.
     * @return array<string, mixed>
     */
    protected function getMap(): array
    {
        return [];
    }

    /**
     * Returns the identity increment value of the table
     */
    protected function getIdentityIncrement(): int
    {
        return 1;
    }

    /**
     * Returns the base select query which can be subsequently filtered, sorted, and paged
     */
    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()} FROM " . $this->getTableName();
    }

    /**
     * Returns the base select query with optional bound params.
     */
    protected function getBaseSelect(QueryOptions $options): SqlParams
    {
        return new SqlParams($this->getBaseQuery($options), []);
    }

    /**
     * @return mixed[]
     */
    protected function getDefaultSort(): array
    {
        return [$this->idField => 'asc'];
    }

    /**
     * Returns a map of properties to columns for selecting/filtering/sorting.
     * @return array<string, mixed>
     */
    protected function getSelectMap(): array
    {
        return [];
    }

    /**
     * Merge additional property information with getSelectMap().
     * Look at the Prop class constructor to see supported options.
     * @return list<Prop>
     */
    protected function getSelectProps(): array
    {
        return [];
    }

    /**
     * Can modify the filter or throw an exception if it is invalid
     * @param mixed[] $filter
     * @return mixed[]
     */
    protected function processFilter(array $filter): array
    {
        return $filter;
    }

    /**
     * Perform any validations/alterations to a set of properties/values to insert/update.
     * When adding entities, default values are merged prior to calling this method.
     * @param mixed[] $data
     * @param list<string|int> $ids
     * @return mixed[]
     */
    protected function processValues(array $data, array $ids): array
    {
        return $data;
    }

    /**
     * Make changes to a row before it is inserted or updated in the database.
     * @param array<string, mixed> $row
     * @param list<string|int> $ids
     * @return array<string, mixed>
     */
    protected function processRow(array $row, array $ids): array
    {
        return $row;
    }

    /**
     * Validate an entity before it is inserted or updated, by throwing an HttpException if it is invalid.
     * $entity contains every writable property in getMap(), with the values that will be saved after
     * processValues() runs. For partial updates, it is the existing entity with the patch merged in.
     * When updating, this is called for each row, and both arrays also contain the row's ID property.
     * $existing contains the existing values of the fields returned by getEntityById(), so it can have
     * properties which aren't in $entity, such as computed properties. $existing is null when inserting.
     * Writable properties which aren't selectable (e.g. a password) aren't in $existing, and are only
     * in $entity for partial updates if the patch sets them.
     * Since the existing entities are selected before the update query runs, rules which must hold
     * when rows are updated concurrently should also be enforced by database constraints.
     * @param array<string, mixed> $entity
     * @param array<string, mixed>|null $existing
     */
    protected function validateEntity(array $entity, ?array $existing): void {}

    /**
     * Throws a 400 HttpException if the same ID is passed more than once.
     * @param list<string|int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        self::checkDuplicateIds($ids);

        return $this->db->deleteFrom($this->getTableName(), [$this->idColumn => $ids]);
    }

    /**
     * Replace one or more rows, or update them via a JSON Merge Patch (https://tools.ietf.org/html/rfc7396) if $partial is true.
     * All mapped properties are required unless $partial is true.
     * If validateEntity() is implemented and any of the rows can't be selected, an HttpException with a
     * 404 status is thrown, and no rows are updated. A 400 HttpException is thrown if the same ID is
     * passed more than once.
     * @param list<string|int> $ids
     * @param mixed[] $data
     */
    public function updateEntities(array $ids, array $data, bool $partial = false): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        self::checkDuplicateIds($ids);

        $data = $this->processValues($data, $ids);
        $row = $partial
            ? self::propertiesToColumns($this->map, $data, complexValues: false)
            : Helpers::allPropertiesToColumns($this->map, $data);

        if ($this->validatesEntities) {
            $ids = $this->validateUpdates($ids, $data, $partial);
        }

        $row = $this->processRow($row, $ids);

        return $this->db->updateRows($this->getTableName(), $row, [$this->idColumn => $ids]);
    }

    /**
     * Calls validateEntity() for each existing row, and returns the IDs of the rows to update.
     * Throws a 404 HttpException if any of the rows can't be selected.
     * @param list<string|int> $ids
     * @param mixed[] $data
     * @return list<string|int>
     */
    private function validateUpdates(array $ids, array $data, bool $partial): array
    {
        $entities = [];

        // select in batches, so the IDs don't exceed the database's bound parameter limit
        foreach (array_chunk($ids, 1000) as $batch) {
            foreach ($this->getEntitiesByIds($batch) as $entity) {
                /** @var int|string $id */
                $id = $entity[$this->idField];
                // the base query can return a row more than once if it joins other tables
                $entities[$id] ??= $entity;
            }
        }

        // compare counts rather than ID values, since the database may match IDs of a different type or case
        if (count($entities) < count($ids)) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        $validatedIds = [];

        foreach ($entities as $entity) {
            /** @var int|string $id */
            $id = $entity[$this->idField];
            $writable = Helpers::getMappedValues($this->selectableMap, $entity);
            // writable properties in a null group are set to null rather than removing the group
            /** @var array<string, mixed> $existing */
            $existing = array_replace_recursive($entity, $writable);
            $updated = $partial ? array_replace_recursive($writable, $data) : $data;
            // writable properties which aren't selectable are only included if they're being set,
            // and the ID is first, but a mapped ID property overrides its value
            $updated = [$this->idField => $id, ...Helpers::getMappedValues($this->map, $updated, fillMissing: false)];
            $this->validateEntity($updated, $existing);
            $validatedIds[] = $id;
        }

        return $validatedIds;
    }

    /**
     * @param list<string|int> $ids
     */
    private static function checkDuplicateIds(array $ids): void
    {
        // passing the same ID more than once is likely a client error
        if (count(array_unique($ids)) !== count($ids)) {
            throw new HttpException('Duplicate ID', StatusCode::BAD_REQUEST);
        }
    }

    /**
     * Returns an array containing the IDs of the inserted rows
     * @param list<array> $entities
     * @return list<int>
     */
    public function addEntities(array $entities): array
    {
        return $this->insertRows(array_map(fn($e) => $this->processEntity($e), $entities));
    }

    /**
     * Runs processValues(), converts the properties to a column/value row, runs validateEntity(),
     * then runs processRow() to return the row to insert. All mapped properties are required.
     * @param mixed[] $data
     * @return array<string, mixed>
     */
    final protected function processEntity(array $data): array
    {
        $data = $this->processValues($data, []);
        $row = Helpers::allPropertiesToColumns($this->map, $data);

        if ($this->validatesEntities) {
            $this->validateEntity(Helpers::getMappedValues($this->map, $data), null);
        }

        return $this->processRow($row, []);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int> the IDs of the inserted rows
     */
    final protected function insertRows(array $rows): array
    {
        if (count($rows) === 0) {
            return [];
        }

        // returning IDs from the insert query ensures they're correct when other sessions insert concurrently
        return $this->db->insertRows($this->getTableName(), $rows, $this->getIdentityIncrement(), $this->idColumn)->ids;
    }

    /**
     * @param string[] $fields
     * @return mixed[]
     */
    public function getEntityById(int|string $id, array $fields = []): array
    {
        $entities = $this->getEntitiesByIds([$id], $fields);

        if (count($entities) === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $entities[0];
    }

    /**
     * @param list<int|string> $ids
     * @param string[] $fields
     * @param mixed[] $sort
     * @return list<array>
     */
    public function getEntitiesByIds(array $ids, array $fields = [], array $sort = []): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->getEntities([$this->idField => $ids], $fields, $sort);
    }

    /**
     * @param mixed[] $filter
     * @param string[] $fields
     * @param mixed[] $sort
     * @return list<array>
     */
    public function getEntities(array $filter = [], array $fields = [], array $sort = [], int $offset = 0, int $limit = 0): array
    {
        $processedFilter = $this->processFilter($filter);
        $selectMap = Helpers::propMapToSelectMap($this->fullPropMap);

        if ($sort === []) {
            $sort = $this->getDefaultSort();
        }

        $fieldProps = Helpers::getFieldPropMap($fields, $this->fullPropMap);
        $queryOptions = new QueryOptions($processedFilter, $filter, $sort, $fieldProps);

        $select = $this->db->select($this->getBaseSelect($queryOptions))
            /** @phpstan-ignore argument.type */
            ->where(self::propertiesToColumns($selectMap, $processedFilter))
            ->orderBy(self::propertiesToColumns($selectMap, $sort, complexValues: false));

        if ($limit !== 0) {
            $select->offset($offset, $limit);
        }

        return Helpers::mapRows($select->query()->getIterator(), $fieldProps);
    }

    /**
     * @param mixed[] $filter
     */
    public function countEntities(array $filter = []): int
    {
        $processedFilter = $this->processFilter($filter);
        $selectMap = Helpers::propMapToSelectMap($this->fullPropMap);

        $prop = new Prop('count', 'COUNT(*)', alias: 'count');
        $queryOptions = new QueryOptions($processedFilter, $filter, [], [$prop]);

        $select = $this->db->select($this->getBaseSelect($queryOptions))
            /** @phpstan-ignore argument.type */
            ->where(self::propertiesToColumns($selectMap, $processedFilter));

        /** @var array{count: int} $row */
        $row = $select->query()->getFirst();

        return $row['count'];
    }

    /**
     * Converts nested properties to an array of columns and values using a map.
     * @param array<string, mixed> $map
     * @param mixed[] $properties
     * @return array<string, mixed>
     */
    public static function propertiesToColumns(
        array $map,
        array $properties,
        bool $ignoreUnmapped = false,
        bool $complexValues = true,
    ): array {
        return Helpers::propsToColumns($map, $properties, $ignoreUnmapped, $complexValues, true);
    }
}
