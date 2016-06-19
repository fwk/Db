# The Query API

If you're familiar with the general SQL syntax, the ```Query``` class will put you comfortable at first sight because its just Object-oriented SQL, bound to [PDO](http://php.net/manual/en/class.pdo.php).

``` php
use Fwk\Db\Query;

// SELECT * FROM users LIMIT 2
$query = Query::factory()
	->select()
	->from('users')
	->limit(2);

// execute the Query
$results = $db->execute($query, $parameters = array());
```

## SELECT

### JOINs

The ```Query``` object has a single method ```join()```. Here is the signature:

``` php
join($table, $localColumn, $foreignColumn = null, $type = Query::JOIN_LEFT, $options = array());
```

- If you don't specify a $foreignColumn, the $localColumn will be used.
- By default, we do LEFT JOINs
- Options:
  - *entity* : ```(string)``` The entity you'd like to use
  - *entityListeners* : ```(array)``` Entity listeners you'd like to use 
  - *skipped* : ```(boolean)``` Data from this table will be skipped 

### Choosing an [Entity](./entities.md) and/or [Listeners](./events.md) 

Specify the Model you need:
``` php
$query->entity('App\Models\User');
```

Specify the Listeners you'd like to use:
``` php
use Fwk\Db\Listeners\Typable;

$query->entity(null, array(new Typable()));
```

This works just like the [Finder](./finder.md) helper.


### Disabling ORM nature (Alternative Fetch Mode)

Sometimes you just need raw data or you think the ORM for this query would considerably slow down your app's performance. No problem! Just specify the [PDO Fetch mode](http://php.net/manual/en/pdostatement.fetch.php) you like:

``` php
$query->setFetchMode(\PDO::FETCH_ASSOC);
```

To go back to the ORM:
``` php
$query->setFetchMode(Query::FETCH_SPECIAL);
```

## INSERT

An example is worth a thousand words:

``` php
$query = Query::factory()
	->insert('users')
	->values(array(
	    'id' => 2
	    /* ... */
  ));
```

``` sql
INSERT INTO users ('id', /* ... */) VALUES (2, /* ... */); 
``` 

## DELETE

``` php
$query = Query::factory()
	->delete('users')
	->where('id = ?');
```

``` sql
DELETE FROM users WHERE id = ?; 
``` 

**Beware!** If no condition is added, it will produce a ```TRUNCATE``` operation on the Table (if supported).

``` php
$query = Query::factory()->delete('users');
```

``` sql
TRUNCATE TABLE users;
``` 

## UPDATE

``` php
$query = Query::factory()
	->update('users')
	->set('email', 'new@email.com')
	->set('username', 'newUsername')
	->where('id = ?');
``` 

``` sql
UPDATE users SET email = ?, username = ? WHERE id = ?
``` 

## WHEREs

You can use three methods: ```where()```, ```andWhere()```  and ```orWhere()```. Each WHERE will be executed with a FIFO logic (First In, First Out).

``` php
$query->where('id = ?')
      ->andWhere('username = ?')
      ->orWhere('email IS NULL');
``` 

Will produce:
``` sql
WHERE (id = ?) AND (username = ?) OR (email IS NULL)
```

You have to be carrefull when grouping WHEREs:
``` php
$query->where('id = ?')
      ->orWhere('username = ? AND email IS NULL');
``` 

Will produce:
``` sql
WHERE (id = ?) OR (username = ? AND email IS NULL)
```

**Recommended**: Even if the Query API allows you to directly pass values in query strings, its *very recommended* to use PDO's query parameters. [You can use question marks like above or named parameters](http://php.net/manual/en/pdo.prepare.php):

``` php
$query->where('id = :id')
      ->orWhere('username = :username AND email IS NULL');
``` 

When using named parameters, be aware that you'll need to pass a keyed array to the query parameters of the ```execute()``` method:

``` php
$results = $db->execute($query, array('id' => 1, 'username' => 'neiluJ'));
```

## GROUP BY

``` php
$query->groupBy($column);
``` 

## ORDER BY

``` php
$query->orderBy($column, 'asc' /* or 'desc' */);
``` 

## LIMIT

``` php
$query->limit($limit, $offset); 
``` 

**Beware!** when using Models with Relations, LIMIT may be ignored and/or create incomplete results.
