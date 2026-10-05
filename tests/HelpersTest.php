<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\Phaster\{Helpers, Prop};
use PHPUnit\Framework\TestCase;
use Teapot\HttpException;

class HelpersTest extends TestCase
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

    public function testSelectMapToPropMap(): void
    {
        $expected = [
            'name' => new Prop('name', 'UserName'),
            'client.id' => new Prop('client.id', 'ClientID'),
            'client.name' => new Prop('client.name', 'ClientName'),
            'client.isDisabled' => new Prop('client.isDisabled', 'isDisabled'),
            'group.type.id' => new Prop('group.type.id', 'GroupTypeID'),
            'group.type.name' => new Prop('group.type.name', 'GroupName'),
        ];

        $this->assertEquals($expected, Helpers::selectMapToPropMap($this->propertyMap));
    }

    public function testPropMapToSelectMap(): void
    {
        $props = [
            new Prop('name', 'UserName'),
            new Prop('client.id', 'ClientID'),
            new Prop('client.name', 'ClientName'),
            new Prop('client.isDisabled', 'isDisabled'),
            new Prop('group.type.id', 'GroupTypeID'),
            new Prop('group.type.name', 'GroupName'),
        ];

        $propMap = Helpers::propListToPropMap($props);
        $this->assertSame($this->propertyMap, Helpers::propMapToSelectMap($propMap));
    }

    public function testMapRows(): void
    {
        /** @phpstan-ignore return.type */
        $usernameMapper = fn(array $row): string => ($row['UserID'] === 1) ? 'testUser' : $row['UserName'];

        $props = [
            new Prop('id', 'UserID'),
            new Prop('username', 'a.UserName', getValue: $usernameMapper, dependsOn: ['id']),
            new Prop('client.id', 'a.ClientID'),
            new Prop('client.isDisabled', 'c.isDisabled', alias: 'Disabled', type: 'bool'),
            new Prop('client.type.id', 'TypeID', nullGroup: true),
            new Prop('dateCreated', 'DateCreatedUTC', timeZone: new \DateTimeZone('UTC')),
        ];

        // test mapping all fields with nullable client.type group
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'Disabled' => 0, 'TypeID' => 2, 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith', 'ClientID' => 2, 'Disabled' => 1, 'TypeID' => null, 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test', 'ClientID' => null, 'Disabled' => null, 'TypeID' => null, 'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'client' => ['id' => 1, 'isDisabled' => false, 'type' => ['id' => 2]], 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'client' => ['id' => 2, 'isDisabled' => true, 'type' => null], 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'client' => ['id' => null, 'isDisabled' => false, 'type' => null], 'dateCreated' => null],
        ];

        $propMap = Helpers::propListToPropMap($props);
        $this->assertSame($expected, Helpers::mapRows($generator(), $propMap));

        // test mapping non-client fields
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith', 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test', 'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'dateCreated' => null],
        ];

        $fieldProps = Helpers::getFieldPropMap(['id', 'username', 'dateCreated'], $propMap);
        $this->assertSame($expected, Helpers::mapRows($generator(), $fieldProps));

        // test nullable client group when selecting client.isDisabled child property
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'Disabled' => 0, 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith', 'ClientID' => 2, 'Disabled' => 1, 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test', 'ClientID' => null, 'Disabled' => null, 'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'client' => ['isDisabled' => false], 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'client' => ['isDisabled' => true], 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'client' => null, 'dateCreated' => null],
        ];

        $props[2] = new Prop('client.id', 'a.ClientID', nullGroup: true);
        $propMap = Helpers::propListToPropMap($props);
        $fieldProps = Helpers::getFieldPropMap(['id', 'username', 'client.isDisabled', 'dateCreated'], $propMap);
        $this->assertSame($expected, Helpers::mapRows($generator(), $fieldProps));

        // test nullable client group when selecting client.type.id grandchild property
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'TypeID' => 2];
            yield ['UserID' => 42, 'UserName' => 'jsmith', 'ClientID' => 2, 'TypeID' => null];
            yield ['UserID' => 1, 'UserName' => 'test', 'ClientID' => null, 'TypeID' => null];
        };

        $expected = [
            ['username' => 'theodoreb', 'client' => ['type' => ['id' => 2]]],
            ['username' => 'jsmith', 'client' => ['type' => null]],
            ['username' => 'testUser', 'client' => null],
        ];

        $fieldProps = Helpers::getFieldPropMap(['username', 'client.type.id'], $propMap);
        $this->assertSame($expected, Helpers::mapRows($generator(), $fieldProps));
    }

    public function testGetFieldPropMap(): void
    {
        $props = [
            new Prop('username', 'UserName', output: false),
            new Prop('client.id', 'ClientID', nullGroup: true),
            new Prop('client.name', 'Company', alias: 'ClientName'),
            new Prop('client.isDisabled', 'isDisabled'),
            new Prop('group.type.id', 'GroupTypeID'),
            new Prop('group.type.name', 'GroupName'),
            new Prop('groupName', 'DiffGroupName'),
        ];

        $propMap = Helpers::propListToPropMap($props);
        $expected = $propMap;
        unset($expected['username']);
        $this->assertSame($expected, Helpers::getFieldPropMap([], $propMap));

        $expected = [
            'group.type.id' => $propMap['group.type.id'],
            'group.type.name' => $propMap['group.type.name'],
        ];

        $this->assertSame($expected, Helpers::getFieldPropMap(['group'], $propMap));

        try {
            Helpers::getFieldPropMap(['username'], $propMap);
            $this->fail('Failed to throw HttpException for field which is not output');
        } catch (HttpException $e) {
            $this->assertSame("'username' is not a valid field", $e->getMessage());
        }

        $expected = ['client.isDisabled', 'groupName', 'client.id'];
        $actual = Helpers::getFieldPropMap(['client.isDisabled', 'groupName'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertTrue($actual['groupName']->output);
        $this->assertFalse($actual['client.id']->output);

        $expected = [
            'client.id' => $propMap['client.id'],
            'client.name' => $propMap['client.name'],
            'client.isDisabled' => $propMap['client.isDisabled'],
            'group.type.id' => $propMap['group.type.id'],
            'group.type.name' => $propMap['group.type.name'],
        ];

        $this->assertSame($expected, Helpers::getFieldPropMap(['client', 'group.type'], $propMap));

        // test selection when dependant field is in a nullable group
        $valueGetter = fn(array $_row): string => '';
        $props[6] = new Prop(
            name: 'groupName',
            col: 'DiffGroupName',
            getValue: $valueGetter,
            dependsOn: ['client.name'],
        );
        $propMap = Helpers::propListToPropMap($props);

        $expected = ['groupName', 'client.name'];
        $actual = Helpers::getFieldPropMap(['groupName'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertTrue($actual['groupName']->output);
        $this->assertFalse($actual['client.name']->output);

        try {
            Helpers::getFieldPropMap(['group.test'], $propMap);
            $this->fail('Failed to throw HttpException for invalid field');
        } catch (HttpException $e) {
            $this->assertSame("'group.test' is not a valid field", $e->getMessage());
        }

        try {
            Helpers::getFieldPropMap([' username'], $propMap);
            $this->fail('Failed to throw HttpException for invalid username field');
        } catch (HttpException $e) {
            $this->assertSame("' username' is not a valid field", $e->getMessage());
        }

        // test dependent fields excluded from output
        $dependencies = ['username', 'client.secret'];
        $props = [
            new Prop('username', 'UserName', output: false),
            new Prop('client.id', 'ClientID', nullGroup: true, getValue: $valueGetter, dependsOn: $dependencies),
            new Prop('client.name', 'Company', alias: 'ClientName'),
            new Prop('client.secret', 'Secret', output: false),
        ];

        $propMap = Helpers::propListToPropMap($props);

        // don't select dependent username field since client.id isn't output anyway
        $expected = ['client.name', 'client.id'];
        $actual = Helpers::getFieldPropMap(['client.name'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertTrue($actual['client.name']->output);
        $this->assertFalse($actual['client.id']->output);

        // subfields which aren't output are only selected since client.id depends on client.secret
        $expected = ['client.id', 'client.name', 'username', 'client.secret'];
        $actual = Helpers::getFieldPropMap(['client'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertTrue($actual['client.id']->output);
        $this->assertFalse($actual['client.secret']->output);

        // username and client.secret should be selected even though they aren't output since client.id depends on them
        $expected = ['client.id', 'client.name', 'username', 'client.secret'];
        $actual = Helpers::getFieldPropMap([], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertTrue($actual['client.id']->output);
        $this->assertTrue($actual['client.name']->output);
        $this->assertFalse($actual['client.secret']->output);
        $this->assertFalse($actual['username']->output);
    }

    public function testPropListToPropMap(): void
    {
        $getValue = fn(array $_row): string => '';

        try {
            $prop = new Prop('test', 'col', getValue: $getValue, dependsOn: ['test']);
            Helpers::propListToPropMap([$prop]);
            $this->fail('Failed to throw exception for Prop that depends on itself');
        } catch (\Exception $e) {
            $this->assertSame("test property cannot depend on itself", $e->getMessage());
        }

        try {
            $prop = new Prop('isBillable', 'billable', getValue: $getValue, dependsOn: ['notAProp']);
            Helpers::propListToPropMap([$prop]);
            $this->fail('Failed to throw exception for invalid dependsOn key value');
        } catch (\Exception $e) {
            $this->assertSame("Invalid dependsOn value 'notAProp' on isBillable property", $e->getMessage());
        }
    }

    public function testPropMapToAliasMap(): void
    {
        $props = [
            new Prop('id', 'UserID'),
            new Prop('username', 'a.UserName'),
            new Prop('client.isDisabled', 'c.isDisabled', alias: 'isClientDisabled', type: 'bool'),
            new Prop('dateCreated', 'DateCreatedUTC', timeZone: new \DateTimeZone('UTC')),
            new Prop('computed', getValue: fn(array $_): bool => true),
            new Prop('computed2', alias: 'aliased', getValue: fn(array $_): bool => false),
        ];

        $propMap = Helpers::propListToPropMap($props);

        $expected = [
            'UserID' => $propMap['id'],
            'UserName' => $propMap['username'],
            'isClientDisabled' => $propMap['client.isDisabled'],
            'DateCreatedUTC' => $propMap['dateCreated'],
            'computed' => $propMap['computed'],
            'aliased' => $propMap['computed2'],
        ];

        $this->assertSame($expected, Helpers::propMapToAliasMap($propMap));
    }

    public function testAllPropertiesToColumns(): void
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

        try {
            Helpers::allPropertiesToColumns($map, []);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required name property", $e->getMessage());
        }

        try {
            $missingClientId = [
                'name' => 'Test Name',
                'client' => [],
            ];

            Helpers::allPropertiesToColumns($map, $missingClientId);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required client.id property", $e->getMessage());
        }

        try {
            $missingGroupTypeId = [
                'name' => 'Test Name',
                'client' => ['id' => null],
                'group' => ['type' => []],
            ];

            Helpers::allPropertiesToColumns($map, $missingGroupTypeId);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required group.type.id property", $e->getMessage());
        }

        $valid = [
            'name' => 'Test Name',
            'client' => ['id' => null],
            'group' => ['type' => ['id' => 234]],
            'extra' => 'extra properties are valid when full map is required',
        ];

        $this->assertSame([], Helpers::allPropertiesToColumns([], $valid));

        $expected = [
            'UserName' => 'Test Name',
            'ClientID' => null,
            'GroupTypeID' => 234,
        ];

        $this->assertSame($expected, Helpers::allPropertiesToColumns($map, $valid));
    }

    public function testGetMappedValues(): void
    {
        $properties = [
            'extra' => 'unmapped properties are excluded',
            'name' => 'Test Name',
            'client' => ['name' => 'Client Name', 'id' => 123, 'extra' => true],
            'group' => null, // null groups are expanded to null properties
        ];

        $expected = [
            'name' => 'Test Name',
            'client' => [
                'id' => 123,
                'name' => 'Client Name',
                'isDisabled' => null,
            ],
            'group' => [
                'type' => [
                    'id' => null,
                    'name' => null,
                ],
            ],
        ];

        $this->assertSame($expected, Helpers::getMappedValues($this->propertyMap, $properties));

        // missing properties are omitted rather than set to null when $fillMissing is false
        $properties = ['extra' => true, 'name' => 'Test Name', 'client' => ['id' => 123]];
        $expected = ['name' => 'Test Name', 'client' => ['id' => 123]];
        $this->assertSame($expected, Helpers::getMappedValues($this->propertyMap, $properties, fillMissing: false));
    }
}
