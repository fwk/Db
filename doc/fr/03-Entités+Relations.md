# Entités

Par défaut, [fwk/Db](http://github.com/fwk/Db) retourne des classes ```\stdClass```. Cependant, il est possible d'assigner des entités spécifiques pour chaque table. Par exemple:

``` php
<?php

$db->table('users')->setDefaultEntity('MyApp\models\User');
```

Cela peut aussi être précisé lors de l'utilisation de l'objet ```Query```:

``` php
<?php

$query->select()->from('users')->where('id = ?')->entity('MyApp\models\User');
```

Cela permet entre-autres de pouvoir diviser ses modèles en fonction de l'usage désiré. En contre partie, la consistence des données peut ne pas être assurée, mais il est peu probable d'avoir à utiliser ce genre de technique dans une même action. 

### Important

Dans la version actuelle, il est impératif que les noms des propriétés de l'entité correspondent aux noms des colonnes des tables concernées !

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

Prenons l'exemple d'une application supposée gérer une collection de livres. Chaque livre est classé dans une catégorie. 

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

Maintenant, l'entité représentant la catégorie du livre est disponible via ```$book->category->get()```. En fait, il est possible d'accéder directement aux propriétés et méthodes de l'entité (comme dans l'exemple ci-dessous) mais ```$book->category``` doit impérativement rester une instance de ```Fwk\Db\Relation```. Pour changer l'entité liée il faut utiliser la méthode ```$book->category->set($obj)```.

``` php
<?php

$book = $db->table('books')->finder('Book')->one(2);

echo $book->category->name; // Sci-Fi
```

## One-to-Many

Pour un site eCommerce, un Article peut avoir plusieurs Attributs. 

``` php
<?php

use Fwk\Db\Relations\One2Many;

class Article extends \stdClass
{
    public $attributes;
    
    public function __construct()
    {
        $this->attributes = new One2Many('id', 'article_id', 'attributes');
    }
}
```

Avec une telle structure, le développeur peut maintenant accéder simplement aux attributs d'un article.

``` php
<?php

$article = $db->table('articles')->finder('Article')->one(42);

foreach($article->attributes as $attr) {
    echo sprintf('- %s: %s', $attr->name, $attr->value);
}
```

### Ajout et Suppression

``` php
<?php

$newAttr = new \stdClass;
$newAttr->name = "test_attribute";
$newAttr->value = "valueOfAttribute";

// ajout
$article->attributes[] = $newAttr;

// suppression du troisième attribut
unset($article->attributes[2]);

$db->table('articles')->save($article);
```

### References

Pour rendre l'utilisation des relations plus pratiques, il est possible de spécifier une colonne de la table dont la valeur servira de clé pour l'accès aux entités de la relation. Avec l'exemple précédent, cette fonctionnalité est très pratique:

``` php
<?php

use Fwk\Db\Relations\One2Many;

class Article extends \stdClass
{
    public $attributes;
    
    public function __construct()
    {
        $this->attributes = new One2Many('id', 'article_id', 'attributes');
        $this->attributes->setReference('name'); // <-- attributes.name = SQL column
    }
}
```

Maintenant, l'attribut que nous avons ajouté tout à l'heure est disponible via:

``` php
<?php

$article->attributes['test_attribute']; // = stdClass

$newAttr = new \stdClass;
$newAttr->name = "test_attribute";
$newAttr->value = "valueOfAttribute";

$article->attributes['new_attr'] = $newAttr;

if(isset($article->attributes['special_offer'])) { /* marketing */ }

unset($article->attributes['attrib']);

$db->table('articles')->save($article);
```

## Many-to-Many


