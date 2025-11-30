<?php

namespace DevTheorem\Phaster;

use Teapot\{HttpException, StatusCode};

/**
 * @internal
 */
class Helpers
{
    /**
     * @param array<string, mixed> $map
     * @return array<string, Prop>
     */
    public static function selectMapToPropMap(array $map, string $context = ''): array
    {
        $propMap = [];

        if ($context !== '') {
            $context .= '.';
        }

        /**
         * @var string|array<string, mixed> $val
         */
        foreach ($map as $key => $val) {
            $newKey = $context . $key;

            if (is_array($val)) {
                $propMap = array_merge($propMap, self::selectMapToPropMap($val, $newKey));
            } else {
                $propMap[$newKey] = new Prop($newKey, $val);
            }
        }

        return $propMap;
    }

    /**
     * @param Prop[] $map
     * @return array<string, mixed>
     */
    public static function propMapToSelectMap(array $map): array
    {
        $selectMap = [];

        foreach ($map as $prop) {
            self::setNestedValue($selectMap, $prop->map, $prop->col);
        }

        return $selectMap;
    }

    /**
     * @param array<string, mixed> $arr
     * @param string[] $path
     */
    private static function setNestedValue(array &$arr, array $path, mixed $value): void
    {
        $_arr = &$arr;

        foreach ($path as $key) {
            /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
            $_arr = &$_arr[$key];
        }

        $_arr = $value;
    }

    /**
     * @param \Generator<int, mixed[]> $rows
     * @param Prop[] $fieldProps
     * @return list<array>
     */
    public static function mapRows(\Generator $rows, array $fieldProps): array
    {
        if (!$rows->valid()) {
            return []; // no rows selected
        }

        $aliasMap = self::propMapToAliasMap($fieldProps);
        $entities = [];

        foreach ($rows as $row) {
            $entity = [];
            /** @var Prop[] $nullParents */
            $nullParents = [];

            foreach ($aliasMap as $colName => $prop) {
                if ($prop->getValue !== null) {
                    $value = ($prop->getValue)($row);
                } else {
                    $value = $row[$colName];

                    if ($prop->type !== null) {
                        settype($value, $prop->type);
                    } elseif (is_string($value) && $prop->timeZone !== false) {
                        $value = (new \DateTimeImmutable($value, $prop->timeZone))->format(\DateTime::ATOM);
                    }
                }

                if ($prop->nullGroup && $value === null) {
                    // only add if there isn't a higher-level null parent
                    $parent = '';

                    foreach ($prop->parents as $parent) {
                        if (isset($nullParents[$parent])) {
                            continue 2;
                        }
                    }

                    $nullParents[$parent] = $prop;
                    continue;
                } elseif (!$prop->output) {
                    continue;
                }

                self::setNestedValue($entity, $prop->map, $value);
            }

            foreach ($nullParents as $prop) {
                $path = array_slice($prop->map, 0, -1);
                self::setNestedValue($entity, $path, null);
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param string[] $fields
     * @param array<string, Prop> $propMap
     * @return array<string, Prop>
     */
    public static function getFieldPropMap(array $fields, array $propMap): array
    {
        /** @var array<string, Prop> $fieldProps */
        $fieldProps = [];
        $dependedOn = [];

        if ($fields === []) {
            // select all default fields
            foreach ($propMap as $prop => $data) {
                if ($data->isDefault) {
                    $fieldProps[$prop] = $data;

                    foreach ($data->dependsOn as $value) {
                        $dependedOn[$value] = true;
                    }
                }
            }
        } else {
            foreach ($fields as $field) {
                /** @var array<string, Prop> $matches */
                $matches = [];

                if (isset($propMap[$field])) {
                    $matches[$field] = $propMap[$field];
                } else {
                    // check for subfields
                    $parent = $field . '.';
                    $length = strlen($parent);

                    foreach ($propMap as $prop => $data) {
                        if (substr($prop, 0, $length) === $parent) {
                            $matches[$prop] = $data;
                        }
                    }

                    if (count($matches) === 0) {
                        throw new HttpException("'{$field}' is not a valid field", StatusCode::BAD_REQUEST);
                    }
                }

                foreach ($matches as $prop => $data) {
                    foreach ($data->dependsOn as $value) {
                        $dependedOn[$value] = true;
                    }

                    $fieldProps[$prop] = $data;
                }
            }

            foreach ($propMap as $prop => $data) {
                if (isset($fieldProps[$prop])) {
                    continue; // already selected
                }

                if ($data->nullGroup) {
                    // check if any selected field is a child
                    assert($data->parents !== []);
                    $parent = $data->parents[array_key_last($data->parents)];

                    foreach ($fieldProps as $field => $_val) {
                        if (str_starts_with($field, $parent)) {
                            $dependedOn[$prop] = true;
                            break;
                        }
                    }
                }
            }
        }

        foreach ($propMap as $prop => $data) {
            if (!isset($fieldProps[$prop]) && isset($dependedOn[$prop])) {
                $data = clone $data;
                $data->output = false;
                $fieldProps[$prop] = $data;
            }
        }

        return $fieldProps;
    }

    /**
     * @param array<Prop> $props
     * @return array<string, Prop>
     */
    public static function propListToPropMap(array $props): array
    {
        $propMap = [];
        $fullDependsList = [];

        foreach ($props as $prop) {
            if (isset($propMap[$prop->name])) {
                throw new \Exception("Duplicate property {$prop->name}");
            }

            foreach ($prop->dependsOn as $field) {
                if ($field === $prop->name) {
                    throw new \Exception("{$prop->name} property cannot depend on itself");
                }

                $fullDependsList[$field] = $prop->name;
            }

            $propMap[$prop->name] = $prop;
        }

        foreach ($fullDependsList as $field => $propName) {
            if (!isset($propMap[$field])) {
                throw new \Exception("Invalid dependsOn value '{$field}' on {$propName} property");
            }
        }

        return $propMap;
    }

    /**
     * @param Prop[] $map
     * @return Prop[]
     */
    public static function propMapToAliasMap(array $map): array
    {
        $aliasMap = [];

        foreach ($map as $prop) {
            $aliasMap[$prop->getOutputCol()] = $prop;
        }

        return $aliasMap;
    }

    /**
     * Converts nested properties to an array of columns and values using a map.
     * All properties in the map are required.
     * @param array<string, mixed> $map
     * @param mixed[] $properties
     * @return array<string, mixed>
     */
    public static function allPropertiesToColumns(array $map, array $properties): array
    {
        // ensure that $map doesn't contain any properties that aren't in $properties
        self::propsToColumns($properties, $map, false, false, false);
        return self::propsToColumns($map, $properties, true, false, true);
    }

    /**
     * @param mixed[] $map
     * @param mixed[] $properties
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
    public static function propsToColumns(
        array $map,
        array $properties,
        bool $ignoreUnmapped,
        bool $complexValues,
        bool $buildColumns,
        string $context = '',
        array $columns = [],
    ): array {
        if ($context !== '') {
            $context .= '.';
        }

        foreach ($properties as $property => $value) {
            $contextProp = $context . $property;

            if (!array_key_exists($property, $map)) {
                if ($ignoreUnmapped) {
                    continue;
                }

                $errMsg = $buildColumns ? 'Invalid' : 'Missing required';
                throw new HttpException("{$errMsg} {$contextProp} property", StatusCode::BAD_REQUEST);
            }

            /** @var array<string, mixed>|scalar|null|object $newMap */
            $newMap = $map[$property]; // might be value

            if (is_array($newMap)) {
                if (!is_array($value)) {
                    if ($buildColumns) {
                        $msg = "Expected $contextProp property to be an object, got " . get_debug_type($value);
                        throw new HttpException($msg, StatusCode::BAD_REQUEST);
                    } else {
                        continue;
                    }
                }

                $columns = self::propsToColumns(
                    map: $newMap,
                    properties: $value,
                    ignoreUnmapped: $ignoreUnmapped,
                    complexValues: $complexValues,
                    buildColumns: $buildColumns,
                    context: $contextProp,
                    columns: $columns,
                );
            } elseif ($buildColumns) {
                if (!is_string($newMap)) {
                    $msg = 'Map values must be arrays or strings, found ' . get_debug_type($newMap) . " for $contextProp property";
                    throw new \Exception($msg);
                } elseif (array_key_exists($newMap, $columns)) {
                    throw new \Exception("Column '$newMap' is mapped to more than one property ($contextProp)");
                } elseif (!$complexValues && !is_null($value) && !is_scalar($value)) {
                    $msg = "Expected $contextProp property to have a scalar value, got " . get_debug_type($value);
                    throw new HttpException($msg, StatusCode::BAD_REQUEST);
                }

                $columns[$newMap] = $value;
            }
        }

        return $columns;
    }
}
