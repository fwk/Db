# Table Finder

The ```Finder``` object is useful when you want to read data from a Table. 

``` php
$finder = $db->table('users')->finder();
``` 

### Configuration

You may specify a [Model](./models.md) class name and/or [Listeners](./events.md) you wish for your results.

``` php
use Fwk\Db\Listeners\Typable;

$finder = $db->table('users')->finder('App\Models\User'); // use a specific model
$finder = $db->table('users')->finder(null, array(new Typable())); // use the Typable listener with stdClass models
$finder = $db->table('users')->finder('App\Models\User', array(new Typable())); // use the Typable listener with specific model
``` 

## All results

The following will fetch every table rows:

``` php
$results = $finder->all();
``` 

## One result

The following will fetch a row from its identifier column (PRIMARY). 
If your table uses a *composite primary key*, your argument must be an array (key/value).

``` php
$user = $finder->one(1); // fetches user where id = 1
``` 

``` php
// table with composite primary key
$user = $finder->one(array(
  'cluster' => 'paris', 
  'id' => 1
)); // fetches user where id = 1 AND cluster = 'paris'
``` 

## Multi-clauses search

The following will fetch rows from a table using a ```AND WHERE``` query for each argument:

``` php
$results = $finder->find(array(
  'email' => 'julien@nitronet.org', 
  'username' => 'neiluJ'
)); // fetches users where email = julien@nitronet.org AND username = neiluJ
``` 

# Limitations

Finder is an utility class to access rapidly to our data. For more compex operations (and/or where, orderBy ...) use the [Query API](./query.md).
