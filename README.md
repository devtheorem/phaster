# Phaster

Phaster is a PSR-7 compatible library for easily making CRUD API endpoints.
It enables mapping API fields to columns in the database or from a select query,
and implementing custom validation or row modification logic.
Built on top of [PeachySQL](https://github.com/devtheorem/peachy-sql).

## Installation

`composer require devtheorem/phaster`

## Usage

Create a class extending `Entities`, and implement the methods to map properties to columns and
define the base SQL query (see example below).
Pass the callable returned by the route handling functions to your Slim or other PSR-7 compatible framework.

```php
<?php

use DevTheorem\Phaster\{Entities, Prop, QueryOptions};

class Users extends Entities
{
    // Return the table to insert/update/delete/select from.
    // If not implemented, the class name will be used as the table name.
    protected function getTableName(): string
    {
        return 'users';
    }

    // Return an array which maps properties to writable column names. Returns an
    // empty array by default, so no properties will be writable if not implemented.
    protected function getMap(): array
    {
        return [
            'username' => 'uname',
            'firstName' => 'fname',
            'lastName' => 'lname',
            'isDisabled' => 'disabled',
            'role' => [
                'id' => 'role_id',
            ],
        ];
    }

    // Return a list of `Prop` objects, which map properties to readable columns and/or values,
    // and enable setting a type to cast the column value to, or defining a virtual property
    // which depends on other columns. Set `output: false` for properties which are only used for
    // filtering/sorting or by other properties, so they're only selected when another property
    // depends on them (writable properties can't set it). See the Prop constructor for the full
    // list of parameters that can be set. Returns an empty array by default.
    protected function getSelectProps(): array
    {
        return [
            new Prop('id', col: 'u.user_id'),
            new Prop('username', col: 'u.uname'),
            new Prop('firstName', col: 'u.fname'),
            new Prop('lastName', col: 'u.lname'),
            new Prop('isDisabled', col: 'u.disabled', type: 'bool'),
            new Prop('role.id', col: 'u.role_id'),
            new Prop('role.name', col: 'r.role_name'),
        ];
    }

    // Return a SELECT SQL query (without a WHERE clause) in order to join other tables when
    // selecting data. If not implemented, mapped columns will be selected from the table
    // returned by getTableName().
    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id";
    }

    // Set the default column/direction for sorting selected results. If not implemented,
    // results will be sorted by the id property in ascending order by default.
    protected function getDefaultSort(): array
    {
        return ['username' => 'asc'];
    }
}
```

If it is necessary to bind parameters in the base query, use `getBaseSelect()` instead:

```php
use DevTheorem\PeachySQL\QueryBuilder\SqlParams;
// ...
protected function getBaseSelect(QueryOptions $options): SqlParams
{
    $sql = "WITH num_orders AS (
                SELECT user_id, COUNT(*) as orders
                FROM Orders
                WHERE category_id = ?
                GROUP BY user_id
            )
            SELECT {$options->getColumns()}
            FROM Users u
            INNER JOIN num_orders n ON n.user_id = u.user_id";

    return new SqlParams($sql, [321]);
}
// ...
```

To validate entities before they are inserted or updated, implement `validateEntity()`. `$entity` contains
every property from `getMap()` with the values that will be saved. For partial updates, this is the existing
entity with the patch merged in. When updating, `$entity` and `$existing` also contain the ID property,
and `$existing` contains the fields returned by `getEntityById()`, such as computed properties.
`$existing` is null when inserting. Writable properties which aren't selectable (such as a password) aren't
in `$existing`, and are only in `$entity` for partial updates if the patch sets them. The ID property must be
output, so the existing entities can be retrieved. If any of the rows being updated can't be selected (e.g.
because the ID doesn't exist or is excluded by `processFilter()`), a 404 `HttpException` is thrown and no rows
are updated.

```php
use Teapot\{HttpException, StatusCode};
// ...
protected function validateEntity(array $entity, ?array $existing): void
{
    if ($existing !== null && $existing['isDisabled'] && $entity['username'] !== $existing['username']) {
        throw new HttpException('Disabled users cannot be renamed', StatusCode::BAD_REQUEST);
    }
}
// ...
```

```php
<?php

use My\DatabaseFactory;
use DevTheorem\Phaster\{Entities, EntitiesFactory};

class MyEntitiesFactory implements EntitiesFactory
{
    public function createEntities($class): Entities
    {
        return new $class(DatabaseFactory::getDb());
    }
}
```

```php
<?php

use DevTheorem\Phaster\RouteHandler;

$phaster = new RouteHandler(new MyEntitiesFactory());

$app->get('/users', $phaster->search(Users::class));
$app->get('/users/{id}', $phaster->getById(Users::class));
$app->post('/users', $phaster->insert(Users::class));
$app->put('/users/{id}', $phaster->update(Users::class));
$app->patch('/users/{id}', $phaster->patch(Users::class));
$app->delete('/users/{id}', $phaster->delete(Users::class));
```

### Example API request/response

GET `https://example.com/api/users?q[firstName]=Ted&q[isDisabled]=0&fields=id,username,role`

```json
{
    "offset": 0,
    "limit": 25,
    "lastPage": true,
    "data": [
        {
            "id": 5,
            "username": "Teddy01",
            "role": {
                "id": 1,
                "name": "Admin"
            }
        },
        {
            "id": 38,
            "username": "only_tj",
            "role": {
                "id": 2,
                "name": "Standard"
            }
        }
    ]
}
```

## Author

Theodore Brown  
<https://theodorejb.me>

## License

MIT
