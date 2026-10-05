<?php

namespace DevTheorem\Phaster\Test\src;

use DevTheorem\Phaster\Prop;
use Teapot\{HttpException, StatusCode};

class ValidatedUsers extends Users
{
    /** @var list<array{entity: array<string, mixed>, existing: array<string, mixed>|null}> */
    public array $validated = [];

    protected function getTableName(): string
    {
        return 'Users';
    }

    protected function getSelectMap(): array
    {
        // birthday is writable but not selectable, like a password would be
        return ['id' => 'user_id', 'name' => 'name', 'weight' => 'weight'];
    }

    protected function getSelectProps(): array
    {
        $isHeavy = function (array $row): bool {
            /** @var array{weight: float|string} $row */
            return $row['weight'] > 100;
        };

        $nameLength = function (array $row): int {
            /** @var array{name: string} $row */
            return strlen($row['name']);
        };

        return [
            ...parent::getSelectProps(),
            new Prop('isHeavy', getValue: $isHeavy, dependsOn: ['weight']),
            new Prop('nameLength', getValue: $nameLength, dependsOn: ['name'], isDefault: false),
        ];
    }

    protected function validateEntity(array $entity, ?array $existing): void
    {
        $this->validated[] = ['entity' => $entity, 'existing' => $existing];

        if ($entity['weight'] <= 0) {
            throw new HttpException('Weight must be positive', StatusCode::BAD_REQUEST);
        }

        if ($existing !== null && $existing['isDisabled'] === true && $entity['name'] !== $existing['name']) {
            throw new HttpException('Disabled users cannot be renamed', StatusCode::BAD_REQUEST);
        }

        if ($existing !== null && $existing['isHeavy'] === true && $entity['isDisabled'] === true) {
            throw new HttpException('Heavy users cannot be disabled', StatusCode::BAD_REQUEST);
        }
    }
}
