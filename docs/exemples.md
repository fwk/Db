# Exemple usages of Fwk\Db

Here are some basic exemples of what's possible to do with Fwk\Db.

## Initialisation de la connexion

The main entry-point of Fwk\Db is the ```Fwk\Db\Connection``` object:

``` php
<?php

$db = new \Fwk\Db\Connection(array(
   'driver' => 'pdo_mysql',
   'host' => 'localhost',
   'user' => 'username',
   'password' => 'passwd',
   'dbname' => 'exemple'
));
```
Configurations options are the same as those required by [Doctrine/DBAL](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html). 

## Browsing tables

The ```Fwk\Db\Table``` object has a simple API to perform actions on a table:

``` php
<?php

$usersTable = $db->table('users');
```

The ```Fwk\Db\Finder``` object has a simple API to perform search on a table:

``` php
<?php

$finder = $usersTable->finder();
$finderUser = $usersTable->finder('App\models\User'); // will use App\models\User instead of \stdClass
$allUsers = $finder->all(); /* returns all rows */
```

```Fwk\Db\Finder``` understands the structure of a table so it's easy to perform basic searches: 

Using the PRIMARY key:

``` php
<?php

$myUser = $finder->one(2); /* returns User->id = 2 */
```

Using a multi-columns PRIMARY key:

``` php
<?php

$myUser = $finder->one(array('id' => 2, 'nickname' => 'neiluj'));
```

Using any column (*mind your indexes* ...):

``` php
<?php

$users = $finder->find(array('active' => true, 'email' => 'test@exemple.com')); /* "AND WHERE" */
```

## Updating Entities

The above code returns entities as  ```\stdClass```:

``` php
<?php

$myUser = $finder->one(2);

print_r($myUser); /* stdClass { id: 2, email: "julien@nitronet.org", nickname: "neiluj" } */
```

Let's update the email and save this to the database:

``` php
<?php

$myUser->email = "j@nitronet.org";

$usersTable->save($myUser); /* => UPDATE users ... WHERE id = 2 */
```

That's it, really.

## Creating Entities

Fwk/Db does NOT validate entities on itself so the way the database schema (constraints, columns, indexes...) is created is *really important* !

``` php
<?php

$newUser = new \stdClass;
$newUser->nickname = "n3wb1e";
$newUser->email = "imnew@example.com";

$usersTable->save($newUser); /* => INSERT INTO users ... */
```

## Removing Entities

Deleting rows cannot be simpler:

``` php
<?php

$myUser = $finder->one(2);

$usersTable->delete($myUser) /* DELETE FROM users WHERE id = 2 */
```

# Query API

Fwk\Db comes with the handy ```Fwk\Db\Query``` object which allows you to create SQL queries the Object-Oriented way. 

``` php
<?php

use Fwk\Db\Query;

// SELECT * FROM users LIMIT 2
$query = Query::factory()
	->select()
	->from('users')
	->limit(2); 

// SELECT id,email FROM users WHERE id = 1 AND active = 1
$query = Query::factory()
	->select('id,email')
	->from('users')
	->where('id = ?')
	->andWhere('active = 1');

// INSERT INTO users VALUES (...)
$query = Query::factory()
	->insert('users')
	->values(array(
	    'id' => 2
	    /* ... */
	));

// UPDATE users SET email = ? WHERE id = 1
$query = Query::factory()
	->update('users')
	->set('email', 'new@email.com')
	->where('id = 1');

// executing the query
$result = $db->execute($query, array(/* query parameters */));
```

[Read more about the Query API here](./query.md).