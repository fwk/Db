# Utilisation basique

Voici en quelques exemples une démonstration de ce qu'il est possible de faire avec [fwk/Db](http://github.com/fwk/Db). Par défaut, [fwk/Db](http://github.com/fwk/Db) utilise ```\stdClass``` comme entité. 

## Initialisation de la connexion

Le point d'entrée de [fwk/Db](http://github.com/fwk/Db) est l'objet ```Connection```. Ses options de configuration sont identiques à celles requises par [Doctrine/DBAL](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html). 

``` php
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

$usersTable = $db->table('users');
```

### Utilisation du Finder

``` php
$finder = $usersTable->finder();
$allUsers = $finder->all(); /* retourne tous les utilisateurs */
```

```Finder``` comprend la structure d'une table et permet donc de faire des requêtes précises sur les clés primaires d'une table. 

Dans le cas d'une clé primaire unique (généralement "ID"):

``` php
$myUser = $finder->one(2); /* retourne user ID: 2 */
```

Dans le cas d'une clé primaire sur plusieurs colonnes:

``` php
$myUser = $finder->one(array('id' => 2, 'nickname' => 'neiluj')); /* retourne user ID: 2 */
```

Enfin, pour une recherche classique sur différents champs pas forcément indexés, la méthode ```find()``` permet de faire des _AND WHERE_ simplement:

``` php
$users = $finder->find(array('email' => 'julien@nitronet.org')); /* retourne un ou plusieurs résultats */
```

## Mise à jour

Comme vu un peu plus haut, le code suivant nous renvoi une entitée ```\stdClass``` correspondant à l'utilisateur ID = 2:

``` php
$myUser = $finder->one(2);

print_r($myUser); /* stdClass { id: 2, email: "julien@nitronet.org", nickname: "neiluj" } */
```

Nous mettons maintenant à jour son email et sauvegardons les changements dans la table:

``` php
$myUser->email = "j@nitronet.org";

$usersTable->save($myUser); /* => UPDATE users ... WHERE id = 2 */
```

C'est tout :)

## Création

[fwk/Db](http://github.com/fwk/Db) laisse au serveur de base de données le soin de valider les requêtes et l'intégrité des données. Il est donc du ressort du développeur de s'assurer que:

* Les entités qu'il sauvegarde comportent bien toutes les informations nécessaires.
* Son schéma de base de données est cohérent.

``` php
$newUser = new \stdClass;
$newUser->nickname = "n3wb1e";
$newUser->email = "imnew@example.com";

$usersTable->save($newUser); /* => INSERT INTO users ... */
```

## Suppression

La suppression d'une entité dans une table est très simple:

``` php
$myUser = $finder->one(2);

$usersTable->delete($myUser) /* DELETE FROM users WHERE id = 2 */
```

# Queries SQL Orientés Objet

[fwk/Db](http://github.com/fwk/Db) fourni un objet ```Query``` permettant de créer de manière orientée objet des queries SQL. 

Voici quelques exemples:

``` php
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

// exécution du query
$result = $db->execute($query, array(/* paramètres */));
```

Le parcours de l'API de l'objet ```Query``` permettra au développeur de comprendre son fonctionnement en détails. Pour faire simple: *pensez SQL* !


