# Fwk\Db Documentation

Fwk\Db tries to mimic a database ORM (Object Relational Mapper) without carrying all its complexity and (finally) let the developer enjoy CRUD operations, object-oriented entities and relations without configuring anything.

## Features

- **[Per-query Models](./finder.md)**: Switch from a [Model](./models.md) to another depending on what's needed.
- **No Model** required at all: ```stdClass``` is minimalist's best friend.
- **No new SQL language**: use a clean **[Object-oriented Query API](./query.md)** or just SQL itself.
- **[Event-Driven Engine](./events.md)**: Intercept any Event and customize the behavior the way you like. Even on ```stdClass``` Models.
- **Proven Foundation**: Built on top of the famous [DataBase Abstraction Layer](http://www.doctrine-project.org/projects/dbal.html) by the [Doctrine Project](http://www.doctrine-project.org) ([DBAL](https://github.com/doctrine/dbal)).


## Examples

First, we initialize the database connection object:

``` php
use Fwk\Db\Connection;

$db = new Connection(array(
   'driver' => 'pdo_mysql',
   'host' => 'localhost',
   'user' => 'username',
   'password' => 'passwd',
   'dbname' => 'example'
));
```

### Basic CRUD

Create a new object:

``` php
$user = (object)array(
   'id' => null,
   'username' => 'neiluJ',
   'email' => 'julien@nitronet.org',
   'password' => 'abcdef'
);

$db->table('users')->save($user); // INSERT INTO users ...
```

Read table:

``` php
$user = $db->table('users')->finder()->one(1); // SELECT * FROM users WHERE id = 1
```

Update table:

``` php
$user->password = 'changed password';

$db->table('users')->save($user); // UPDATE users SET ... WHERE id = 1
```

Delete from table:

``` php
$db->table('users')->delete($user); // DELETE FROM users WHERE id = 1
```

