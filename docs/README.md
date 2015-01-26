# Fwk\Db Documentation

Fwk\Db tries to mimic a database ORM (Object Relational Mapper) without carrying all its complexity and (finally) let the developer enjoy CRUD operations, object-oriented entities and relations without configuring anything.

## Features

- **[Per-query Models](./query.md)**: Switch from a [Model](./models.md) to another depending on what's needed.
- **No Model** required at all: ```stdClass``` is minimalist's best friend.
- **No new SQL language**: use a clean **[Object-oriented Query API](./query.md)** or just SQL itself.
- **[Event-Driven Engine](./events.md)**: Intercept any Event and customize the behavior the way you like. Even on ```stdClass``` Models.
- **Proven Foundation**: Built on top of the famous [DataBase Abstraction Layer](http://www.doctrine-project.org/projects/dbal.html) by the [Doctrine Project](http://www.doctrine-project.org) ([DBAL](https://github.com/doctrine/dbal)).

## Examples


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

Voilà ! Vous pouvez dès à présent utiliser [fwk/Db](http://github.com/fwk/Db) sans avoir à créer des fichiers de configuration/entités/schémas supplémentaires.

## Recherche dans une table

[fwk/Db](http://github.com/fwk/Db) est fourni avec un objet ```Finder``` permettant de chercher des données dans une table. Cet objet se récupère depuis l'objet ```Table```.

``` php
<?php

$usersTable = $db->table('users');
```

### Utilisation du Finder

``` php
<?php

$finder = $usersTable->finder();
$finderUser = $usersTable->finder('App\models\User'); // retournera l'entité App\models\User au lieu de \stdClass
$allUsers = $finder->all(); /* retourne tous les utilisateurs */
```

```Finder``` comprend la structure d'une table et permet donc de faire des requêtes précises sur les clés primaires d'une table. 

Dans le cas d'une clé primaire unique (généralement "ID"):

``` php
<?php

$myUser = $finder->one(2); /* retourne user ID: 2 */
```
