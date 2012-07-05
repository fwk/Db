# Installation

Le moyen le plus simple d'installer [fwk/Db](http://github.com/fwk/Db) est [Composer](http://getcomposer.org):

```
{
    "require": {
        "fwk/Db": ">=0.1.0",
    }
}
```

Dans le cas où vous ne souhaitez pas utiliser Composer, vous pouvez  [télécharger](https://github.com/fwk/Db/zipball/master) le repository et l'ajouter à votre ```include_path``` [PSR-0 compatible](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md).

## Vendors

Lors d'une installation manuelle (càd. sans Composer), vous devrez aussi télécharger les libraries suivantes et les ajouter à votre ```include_path```:

* [fwk/Events](https://github.com/fwk/Events/zipball/master)
* [doctrine/common](https://github.com/doctrine/common/zipball/master)
* [doctrine/dbal](https://github.com/doctrine/dbal/zipball/master)

# Tests

L'exécution des Tests implique une installation par Composer.

