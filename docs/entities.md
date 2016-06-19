# Entities and Relations

By default Fwk\Db returns ```\stdClass``` entities. It is of course possible to use a custom entity:

``` php
<?php

$db->table('users')->setDefaultEntity('MyApp\Models\User');
```

This could also be defined on a per-query basis using the [Query API](./query.md) for very specific cases:

``` php
<?php

$query->select()->from('users')->where('id = ?')->entity('MyApp\Models\User');
```

### Important

Entities properties names MUST match exactly database columns names!

## Properties access

When using ```private``` ou ```protected``` properties getters/setters must be defined: 

``` php
<?php
namespace MyApp\Models;

class User
{
    protected $id; /* @see getId/setId */
    
    public $nickname; /* public ! */
    
    private $email; /* never updated */
    
    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }
}
```

# Relations

Fwk\Db supports classic database relations (One-to-One, One-to-Many and Many-To-Many) that can be loaded _when needed_ (```LAZY```) or _always loaded_ (```EAGER```).

## One-to-One

Let's imagine a library where every book is categorized within a category: 

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

Now the category entity can be reached using ```$book->category->get()```. 
It is possible to access the category's properties (like the exemple bellow) but ```$book->category``` MUST stay an instance of ```Fwk\Db\Relation```. To define the category entity simply do ```$book->category->set($category)```.

``` php
<?php

$book = $db->table('books')->finder('Book')->one(2);

echo $book->category->name; // Sci-Fi
```

## One-to-Many

On a e-Commerce application a Product can have many Attributes. 

``` php
<?php

use Fwk\Db\Relations\One2Many;

class Product extends \stdClass
{
    public $attributes;
    
    public function __construct()
    {
        $this->attributes = new One2Many('id', 'article_id', 'attributes');
    }
}
```

Now let's browse attributes:
``` php
<?php

$article = $db->table('products')->finder('Product')->one(42);

foreach($article->attributes as $attr) {
    echo sprintf('- %s: %s', $attr->name, $attr->value);
}
```

### Adding and Removing related entities

``` php
<?php

$newAttr = new \stdClass;
$newAttr->name = "test_attribute";
$newAttr->value = "valueOfAttribute";

// adding
$article->attributes[] = $newAttr;

// removing the third attribute
unset($article->attributes[2]);

$db->table('articles')->save($article);
```

### References

To ease the use of relations, you can specify a column as a key for related entities:

``` php
<?php

use Fwk\Db\Relations\One2Many;

class Product extends \stdClass
{
    public $attributes;
    
    public function __construct()
    {
        $this->attributes = new One2Many('id', 'article_id', 'attributes');
        $this->attributes->setReference('name'); // <-- attributes.name = SQL column
    }
}
```

Now attributes can be easilly browsed:
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

