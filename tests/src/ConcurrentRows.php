<?php

namespace DevTheorem\Phaster\Test\src;

use DevTheorem\Phaster\{Entities, Prop};

class ConcurrentRows extends Entities
{
    protected function getTableName(): string
    {
        return 'ConcurrentTest';
    }

    protected function getMap(): array
    {
        return [
            'worker' => 'worker',
            'batch' => 'batch',
            'seq' => 'seq',
        ];
    }

    protected function getSelectProps(): array
    {
        return [
            new Prop('id', 'id'),
            new Prop('worker', 'worker'),
            new Prop('batch', 'batch'),
            new Prop('seq', 'seq'),
        ];
    }
}
