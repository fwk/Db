# Entités

Par défaut, [fwk/Db](http://github.com/fwk/Db) retourne des classes ```\stdClass```. Cependant, il est possible d'assigner des entités spécifiques pour chaque table. Par exemple:

``` php
<?php

$db->table('users')->setDefaultEntity('MyApp\models\User');
```

Cela peut également être précisé lors de l'utilisation de l'objet ```Query```:

``` php
<?php

$query->select()->from('users')->where('id = ?')->entity('MyApp\models\User');
```

Cela permet entre-autres de pouvoir diviser ses modèles en fonction de l'usage désiré. En contre partie, la consistence des données peut ne pas être assurée, mais il est peu probable d'avoir à utiliser ce genre de technique dans une même action. 

## Accessibilité des propriétés de l'entité

La visibilité choisie par le développeur influencera le passage des données entre un Query et les propriété de l'entité. Par conséquent, si les propriétées sont ```private``` ou ```protected```, des getters/setters devront être présents. 

Exemple:

``` php
<?php
namespace MyApp\models;

class User
{
    protected $id; /* @see getId/setId */
    
    public $nickname; /* public ! */
    
    private $email; /* jamais renseigné */
    
    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }
}
```

# Relations

[fwk/Db](http://github.com/fwk/Db) supporte les trois types de relations les plus communs, pouvant être chargées _à la demande_ (```LAZY```) ce qui permet d'éviter le traitement de données non utilisées, ou _systématiquement_ (```EAGER```) assurant des modèles "complets" à chaque fois.

## One-to-One

Prenons l'exemple d'une application supposée gérer une collection de livres. Chaque livre est classé dans une catégorie principale. 

``` php
<?php

use Fwk\Db\Relations\One2One;

class Book extends \stdClass
{
    public $category;
    
    public function __construct()
    {
        $this->category = new One2One('category_id', 'id', 'categories');
    }
}
```

Maintenant, l'entitée représentant la catégorie du livre est disponible via ```$book->category->fetch()```. En fait, il est possible d'accéder directement aux propriétés et méthodes de l'entité comme dans l'exemple ci-dessous, mais ```$book->category``` doit impérativement rester une instance de ```Fwk\Db\Relation```.

``` php
<?php

$booksTable = $db->table('books');
$booksTable->setDefaultEntity('Book');
$book = $db->table('books')->finder()->one(2);

echo $book->category->name; // Sci-Fi
```

## One-to-Many



## Many-to-Many


