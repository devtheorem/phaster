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
     * @param list<string|int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        return $this->db->deleteFrom($this->getTableName(), [$this->idColumn => $ids]);
    }

    /**
     * Replace one or more rows, or update them via a JSON Merge Patch (https://tools.ietf.org/html/rfc7396) if $partial is true
     * @param list<string|int> $ids
     * @param mixed[] $data
     */
    public function updateEntities(array $ids, array $data, bool $partial = false): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        $row = $this->processEntity($data, $ids, $partial);

        return $this->db->updateRows($this->getTableName(), $row, [$this->idColumn => $ids]);
    }

    /**
     * Returns an array containing the IDs of the inserted rows
     * @param list<array> $entities
     * @return list<int>
     */
    public function addEntities(array $entities): array
    {
        return $this->insertRows(array_map(fn($e) => $this->processEntity($e, []), $entities));
    }

    /**
     * Runs processValues(), converts the properties to a column/value row, then runs processRow().
     * All mapped properties are required unless $partial is true.
     * @param mixed[] $data
     * @param list<string|int> $ids
     * @return array<string, mixed>
     */
    final protected function processEntity(array $data, array $ids, bool $partial = false): array
    {
        $data = $this->processValues($data, $ids);
        $row = $partial
            ? self::propertiesToColumns($this->map, $data, complexValues: false)
            : Helpers::allPropertiesToColumns($this->map, $data);

        return $this->processRow($row, $ids);
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

        $prop = new Prop('count', 'COUNT(*)', false, true, 'count');
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
