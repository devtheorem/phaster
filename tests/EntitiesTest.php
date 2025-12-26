<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\Phaster\{Entities, Helpers};
use PHPUnit\Framework\TestCase;
use Teapot\HttpException;

class EntitiesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $propertyMap = [
        'name' => 'UserName',
        'client' => [
            'id' => 'ClientID',
            'name' => 'ClientName',
            'isDisabled' => 'isDisabled',
        ],
        'group' => [
            'type' => [
                'id' => 'GroupTypeID',
                'name' => 'GroupName',
            ],
        ],
    ];

    public function testPropertiesToColumns(): void
    {
        $q = [
            'name' => 'testUser',
            'client' => [
                'name' => [
                    'lk' => 'test%',
                    'nl' => 'test1%',
                ],
                'isDisabled' => '0',
            ],
            'group' => [
                'type' => [
                    'name' => ['group 1', 'group 2'],
                ],
            ],
        ];

        $expected = [
            'UserName' => 'testUser',
            'ClientName' => [
                'lk' => 'test%',
                'nl' => 'test1%',
            ],
            'isDisabled' => '0',
            'GroupName' => ['group 1', 'group 2'],
        ];

        $this->assertSame($expected, Entities::propertiesToColumns($this->propertyMap, $q));

        $sort = [
            'client' => [
                'name' => 'asc',
                'isDisabled' => 'desc',
            ],
            'name' => 'asc',
        ];

        $sortExpectation = ['ClientName' => 'asc', 'isDisabled' => 'desc', 'UserName' => 'asc'];
        $this->assertSame($sortExpectation, Entities::propertiesToColumns($this->propertyMap, $sort));
        $this->assertSame([], Entities::propertiesToColumns($this->propertyMap, []));

        $binaryMap = [
            'prop' => 'SomeColumn',
            'binaryProp' => 'BinaryColumn',
        ];

        $binaryObj = [
            'prop' => 'val',
            'binaryProp' => ['abc123', 1],
        ];

        $binaryExpectation = [
            'SomeColumn' => 'val',
            'BinaryColumn' => ['abc123', 1],
        ];

        $this->assertSame($binaryExpectation, Entities::propertiesToColumns($binaryMap, $binaryObj));

        try {
            Helpers::allPropertiesToColumns($binaryMap, $binaryObj);
            $this->fail('Failed to throw exception for non-scalar property value');
        } catch (\Exception $e) {
            $this->assertSame('Expected binaryProp property to have a scalar value, got array', $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }
    }

    public function testInvalidMap(): void
    {
        $invalidTypes = [null, true, false, 10, 1.5, new \stdClass()];

        foreach ($invalidTypes as $type) {
            try {
                Entities::propertiesToColumns(['prop' => $type], ['prop' => true]);
                $this->fail('Failed to throw exception for invalid map value');
            } catch (\Exception $e) {
                $msg = "Map values must be arrays or strings, found " . get_debug_type($type) . " for prop property";
                $this->assertSame($msg, $e->getMessage());
            }
        }

        $duplicateColumns = [
            'prop1' => 'TestCol',
            'prop2' => [
                'subProp' => 'TestCol',
            ],
        ];

        $properties = [
            'prop1' => 'Hello',
            'prop2' => [
                'subProp' => 'World',
            ],
        ];

        try {
            Helpers::allPropertiesToColumns($duplicateColumns, $properties);
            $this->fail('Failed to throw exception for duplicate map column');
        } catch (\Exception $e) {
            $this->assertSame("Column 'TestCol' is mapped to more than one property (prop2.subProp)", $e->getMessage());
        }
    }

    public function testInvalidPropertiesToColumns(): void
    {
        try {
            $badQ = [
                'client' => [
                    'lk' => 'foo%',
                ],
            ];

            Entities::propertiesToColumns($this->propertyMap, $badQ);
            $this->fail('Failed to throw exception for invalid property');
        } catch (HttpException $e) {
            $this->assertSame("Invalid client.lk property", $e->getMessage());
        }

        try {
            $badData = ['client' => null];
            Entities::propertiesToColumns($this->propertyMap, $badData);
            $this->fail('Failed to throw exception for null property');
        } catch (HttpException $e) {
            $this->assertSame("Expected client property to be an object, got null", $e->getMessage());
        }

        try {
            $badData = ['client' => 'some string'];
            Entities::propertiesToColumns($this->propertyMap, $badData);
            $this->fail('Failed to throw exception for invalid string property');
        } catch (\Exception $e) {
            $this->assertSame("Expected client property to be an object, got string", $e->getMessage());
        }
    }

    public function testPartialPropertiesToColumns(): void
    {
        $map = [
            'name' => 'UserName',
            'client' => [
                'id' => 'ClientID',
            ],
            'group' => [
                'type' => [
                    'id' => 'GroupTypeID',
                ],
            ],
        ];

        $partialProperties = [
            // doesn't have all properties in map
            'name' => 'Test Name',
            'group' => ['type' => ['id' => 321]],
            'extra' => 'extra properties are valid',
        ];

        $expected = [
            'UserName' => 'Test Name',
            'GroupTypeID' => 321,
        ];

        $this->assertSame($expected, Entities::propertiesToColumns($map, $partialProperties, true));
    }
}
