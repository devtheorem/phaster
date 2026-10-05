<?php

namespace DevTheorem\Phaster\Test\src;

use DevTheorem\Phaster\{Entities, Prop, QueryOptions};

class ModernUsers extends Entities
{
    /** @var array{ids: list<int>, rows: list<array<string, mixed>>}|null */
    public ?array $inserted = null;

    protected function getTableName(): string
    {
        return 'Users';
    }

    protected function getMap(): array
    {
        return [
            'name' => 'name',
            'birthday' => 'dob',
            'weight' => 'weight',
            'isDisabled' => 'is_disabled',
        ];
    }

    protected function getSelectProps(): array
    {
        $getValue = function (array $row): float {
            /** @var array{weight: int} $row */
            return $row['weight'] + 1;
        };

        return [
            new Prop('id', 'u.user_id'),
            new Prop('name', 'name', alias: 'username'),
            new Prop('weight', 'weight'),
            new Prop('isDisabled', 'is_disabled', type: 'bool'),
            new Prop('computed', getValue: $getValue, dependsOn: ['weight']),
            new Prop('thing.id', 'ut.thing_id', true),
            new Prop('thing.uid', 'ut.user_id', alias: 'thing_user'),
        ];
    }

    protected function getDefaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM Users u
                LEFT JOIN UserThings ut ON ut.user_id = u.user_id";
    }

    public function addEntities(array $entities): array
    {
        $rows = [];
        $existingIds = [];

        foreach ($entities as $key => $entity) {
            if (($entity['name'] ?? null) === 'Modern user 2') {
                $existingIds[$key] = -42; // don't insert row for this item
            } else {
                $rows[] = $this->processEntity($entity);
            }
        }

        $ids = $this->insertRows($rows);
        $this->inserted = ['ids' => $ids, 'rows' => $rows];

        foreach ($existingIds as $offset => $id) {
            array_splice($ids, $offset, 0, [$id]);
        }
        return $ids;
    }

    protected function processValues(array $data, array $ids): array
    {
        if (count($ids) === 0) {
            if ($data['name'] === 'Modern user 3') {
                $data['name'] = 'Modern user 3 modified';
            }
        }

        return $data;
    }
}
