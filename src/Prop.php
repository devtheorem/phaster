<?php

namespace DevTheorem\Phaster;

use Closure;
use DateTimeZone;

class Prop
{
    /**
     * This is the only property which can be modified externally.
     * @internal
     */
    public bool $output;

    /**
     * @var string[]
     */
    public readonly array $map;

    /**
     * @var string[]
     */
    public readonly array $parents;

    /**
     * @param string[] $dependsOn
     */
    public function __construct(
        public readonly string $name,
        public readonly string $col = '',
        public readonly bool $nullGroup = false,
        public readonly bool $isDefault = true,
        public readonly string $alias = '',
        public readonly ?string $type = null,
        public readonly DateTimeZone|false|null $timeZone = false,
        public readonly ?Closure $getValue = null,
        public readonly array $dependsOn = [],
        bool $output = true,
    ) {
        $this->output = $output;
        $this->map = explode('.', $name);
        $depth = count($this->map);
        $parent = '';
        $parents = [];

        for ($i = 0; $i < $depth - 1; $i++) {
            $parent .= $this->map[$i] . '.';
            $parents[] = $parent;
        }
        $this->parents = $parents;

        if ($nullGroup && $depth < 2) {
            throw new \Exception("nullGroup cannot be set on top-level {$name} property");
        }

        if ($getValue === null) {
            if ($col === '') {
                throw new \Exception("col cannot be blank on {$name} property without getValue function");
            } elseif (count($dependsOn) !== 0) {
                throw new \Exception("dependsOn cannot be used on {$name} property without getValue function");
            }
        } else {
            if ($type !== null) {
                throw new \Exception("type cannot be set on {$name} property along with getValue");
            } elseif ($timeZone !== false) {
                throw new \Exception("timeZone cannot be set on {$name} property along with getValue");
            }
        }

        if ($type !== null) {
            $allowedTypes = ['bool', 'int', 'float', 'string'];

            if (!in_array($type, $allowedTypes, true)) {
                throw new \Exception("type for {$name} property must be bool, int, float, or string");
            }

            if ($timeZone !== false) {
                throw new \Exception("timeZone cannot be set on {$name} property along with type");
            }
        }
    }

    public function getOutputCol(): string
    {
        if ($this->alias) {
            return $this->alias;
        } elseif ($this->col === '') {
            return $this->name;
        }

        $col = explode('.', $this->col);
        return array_pop($col);
    }
}
