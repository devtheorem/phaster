<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\src\{LegacyUsers, ModernUsers, Users};
use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase
{
    abstract public static function dbProvider(): PeachySql;

    public static function tearDownAfterClass(): void
    {
        $db = static::dbProvider();

        foreach (['UserThings', 'Users'] as $table) {
            $db->query("DROP TABLE IF EXISTS $table");
        }
    }

    public static function entitiesProvider(): Users
    {
        return new Users(static::dbProvider());
    }

    public static function legacyUsersProvider(): LegacyUsers
    {
        return new LegacyUsers(static::dbProvider());
    }

    public function testAddEntities(): void
    {
        $db = static::dbProvider();
        $entities = new Users($db);

        try {
            $entities->addEntities([[
                'name' => 'My name',
                'birthday' => '2017-03-05',
            ]]);

            throw new \Exception('Failed to throw exception for missing required property');
        } catch (\Exception $e) {
            $this->assertSame('Missing required weight property', $e->getMessage());
        }

        $users = [
            [
                'name' => 'My Name',
                'birthday' => '2017-03-05',
                'weight' => 130.0,
                // leave out isDisabled since it's optional
            ],
            [
                'name' => 'Another Name',
                'birthday' => '2016-04-06',
                'weight' => 200.0,
                'isDisabled' => true,
            ],
        ];

        $ids = $entities->addEntities($users);

        $expected = array_map(function ($u, $i) use ($db) {
            return array_merge(['id' => $i], $u, [
                'weight' => $db->options->floatSelectedAsString ? (string) $u['weight'] : $u['weight'],
                'isDisabled' => $u['isDisabled'] ?? false,
            ]);
        }, $users, $ids);

        $this->assertSame($expected, $entities->getEntitiesByIds($ids));
    }

    public function testUpdateById(): void
    {
        $db = static::dbProvider();
        $entities = new Users($db);

        try {
            // optional properties should still be required when replacing an entity
            $entities->updateById(0, [
                'name' => 'My Name',
                'birthday' => '2017-03-05',
                'weight' => 130.0,
            ]);

            throw new \Exception('Failed to throw exception for missing property');
        } catch (\Exception $e) {
            $this->assertSame('Missing required isDisabled property', $e->getMessage());
        }

        $user = [
            'name' => 'Wrong Name',
            'birthday' => '2015-04-06',
            'weight' => 250.0,
            'isDisabled' => true,
        ];

        $id = $entities->addEntities([$user])[0];

        $newUser = [
            'id' => $id,
            'name' => 'Right Name',
            'birthday' => '2016-05-07',
            'weight' => 215.0,
            'isDisabled' => false,
        ];

        if ($db->options->floatSelectedAsString) {
            $newUser['weight'] = (string) $newUser['weight'];
        }

        $entities->updateById($id, $newUser);
        $this->assertSame($newUser, $entities->getEntityById($id));
    }

    public function testPatchByIds(): void
    {
        $db = static::dbProvider();
        $entities = new Users($db);

        $users = [
            [
                'name' => 'Canada 🪿',
                'birthday' => '2024-10-21',
                'weight' => 10.55,
            ],
            [
                'name' => 'Rutt 🫎',
                'birthday' => '2015-02-03',
                'weight' => 1500.0,
            ],
        ];

        $ids = $entities->addEntities($users);

        $expected = array_map(function ($u, $i) use ($db) {
            return array_merge(['id' => $i], $u, [
                'weight' => $db->options->floatSelectedAsString ? (string) $u['weight'] : $u['weight'],
                'isDisabled' => false,
            ]);
        }, $users, $ids);

        $this->assertSame($expected, $entities->getEntitiesByIds($ids));

        $entities->patchByIds($ids, [
            'weight' => 125.0,
        ]);

        $newExpected = array_map(function ($u) use ($db) {
            return array_merge($u, [
                'weight' => $db->options->floatSelectedAsString ? '125' : 125.0,
            ]);
        }, $expected);

        $this->assertSame($newExpected, $entities->getEntitiesByIds($ids));

        $entities->deleteByIds($ids);
        $this->assertSame([], $entities->getEntitiesByIds($ids));
    }

    public function testEmptyQueries(): void
    {
        $entities = static::entitiesProvider();
        $ids = $entities->addEntities([]);
        $this->assertSame([], $entities->getEntitiesByIds($ids));
        $this->assertSame(0, $entities->patchByIds($ids, ['weight' => 10]));
        $this->assertSame(0, $entities->deleteByIds($ids));
    }

    public function testDuplicateError(): void
    {
        $entities = static::entitiesProvider();

        $users = [
            [
                'name' => 'Conflicting Name',
                'birthday' => '2010-11-12',
                'weight' => 111.0,
            ],
            [
                'name' => 'Conflicting Name',
                'birthday' => '2011-12-13',
                'weight' => 121.0,
            ],
        ];

        try {
            $entities->addEntities($users);
            throw new \Exception('Failed to throw duplicate name exception');
        } catch (\Exception $e) {
            $this->assertStringContainsStringIgnoringCase(' duplicate ', $e->getMessage());
        }
    }

    public function testConstraintError(): void
    {
        $db = static::dbProvider();

        $user = [
            'name' => 'Some Name',
            'birthday' => '2001-06-17',
            'weight' => 156,
        ];

        $entities = new Users($db);
        $id = $entities->addEntities([$user])[0];
        $db->insertRow('UserThings', ['user_id' => $id]);

        try {
            $entities->deleteByIds([$id]);
            throw new \Exception('Failed to throw constraint violation exception');
        } catch (\Exception $e) {
            $this->assertStringContainsStringIgnoringCase(' constraint ', $e->getMessage());
        }
    }

    public function testGetEntities(): void
    {
        $db = static::dbProvider();
        $entities = new Users($db);
        $users = [];

        for ($i = 1; $i <= 50; $i++) {
            $users[] = [
                'name' => "User {$i}",
                'birthday' => '2000-02-04',
                'weight' => $i * 10,
            ];
        }

        $entities->addEntities($users);

        $actual = array_map(function (array $u): array {
            unset($u['id']);
            return $u;
        }, $entities->getEntities(['weight' => ['gt' => 250]], [], ['weight' => 'desc'], 10, 5));

        $expected = [];

        for ($j = 40; $j > 35; $j--) {
            $weight = $j * 10.0;
            $expected[] = [
                'name' => "User {$j}",
                'birthday' => '2000-02-04',
                'weight' => $db->options->floatSelectedAsString ? (string) $weight : $weight,
                'isDisabled' => false,
            ];
        }

        $this->assertSame($expected, $actual);
    }

    public function testCountEntities(): void
    {
        $entities = static::entitiesProvider();
        $users = [];

        for ($i = 1; $i <= 20; $i++) {
            $users[] = [
                'name' => "Count user {$i}",
                'birthday' => '2000-03-04',
                'weight' => $i * 10,
            ];
        }

        $entities->addEntities($users);
        $actual = $entities->countEntities(['weight' => ['le' => 100], 'name' => ['lk' => 'Count user %']]);
        $this->assertSame(10, $actual);
    }

    public function testLegacyUsers(): void
    {
        $entities = static::legacyUsersProvider();
        $users = [];

        for ($i = 1; $i <= 20; $i++) {
            $users[] = [
                'name' => "Legacy user {$i}",
                'birthday' => '2000-02-19',
                'isDisabled' => true,
                'weight' => $i * 10,
            ];
        }

        $ids = $entities->addEntities($users);
        $actual = $entities->getEntities(['id' => [$ids[3], $ids[4]], 'customFilter' => $ids[3]]);

        $expected = [
            [
                'id' => $ids[4],
                'name' => 'Legacy user 5',
                'birthday' => '2001-03-20',
                'thing' => ['id' => null],
            ],
        ];

        $this->assertSame($expected, $actual);

        $actual = $entities->getEntities(['id' => $ids[4]], ['name']);
        $this->assertSame([['name' => 'Legacy user 5']], $actual);
    }

    public function testModernUsers(): void
    {
        $db = static::dbProvider();
        $entities = new ModernUsers($db);
        $users = [];

        for ($i = 1; $i <= 10; $i++) {
            $users[] = [
                'name' => "Modern user {$i}",
                'birthday' => '2000-02-20',
                'isDisabled' => false,
                'weight' => $i * 10,
            ];
        }

        $ids = $entities->addEntities($users);
        $this->assertSame(-42, $ids[1]); // manually set ID in processValues

        $db->insertRow('UserThings', ['user_id' => $ids[3]]);

        $actual = $entities->getEntitiesByIds([$ids[2], $ids[3]], ['id', 'name', 'isDisabled', 'computed', 'thing.uid']);

        $expected = [
            [
                'id' => $ids[3],
                'name' => 'Modern user 4',
                'isDisabled' => false,
                'computed' => 41.0,
                'thing' => [
                    'uid' => $ids[3],
                ],
            ],
            [
                'id' => $ids[2],
                'name' => 'Modern user 3 modified',
                'isDisabled' => false,
                'computed' => 31.0,
                'thing' => null,
            ],
        ];

        $this->assertSame($expected, $actual);
    }
}
