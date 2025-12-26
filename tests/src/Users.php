<?php

namespace DevTheorem\Phaster\Test\src;

use DevTheorem\Phaster\Entities;
use DevTheorem\Phaster\Prop;

class Users extends Entities
{
    protected function getMap(): array
    {
        return [
            'name' => 'name',
            'birthday' => 'dob',
            'weight' => 'weight',
            'isDisabled' => 'is_disabled',
        ];
    }

    protected function getSelectMap(): array
    {
        return ['id' => 'user_id', ...$this->getMap()];
    }

    protected function getSelectProps(): array
    {
        return [
            new Prop('isDisabled', 'is_disabled', type: 'bool'),
        ];
    }

    protected function processValues(array $data, array $ids): array
    {
        if (!$ids && !isset($data['isDisabled'])) {
            $data['isDisabled'] = false;
        }

        return $data;
    }
}
