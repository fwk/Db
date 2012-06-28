# Introduction

[fwk/Db](http://github.com/fwk/Db) est un outil simple et puissant pour la représentation d'objets en bases de données de type SQL. Bien que beaucoup d'ORMs existent pour PHP ([Propel](http://www.propelorm.org/), [Doctrine](http://www.doctrine-project.org/projects/orm.html), [Zend_Db](http://framework.zend.com/manual/en/zend.db.html), ...) leur prise en main est parfois complexe et oblige le développeur à réapprendre des concepts spécifiques pour utiliser un outil commun: une base de donnée. 

Partant de ce principe, [fwk/Db](http://github.com/fwk/Db) permet:

* Récupération de données sous forme d'objets (entités)
* Sauvegarde/Modification des entités
* Gestion des relations (One to One, One to Many, Many to Many)
* Interface OOP pour créer des Queries SQL
* Recherche et Itération dans les tables simplifiées

[fwk/Db](http://github.com/fwk/Db) se base sur [Doctrine/DBAL](http://www.doctrine-project.org/projects/dbal.html) (Database Abstraction Layer) ce qui lui permet une intéropérabilité [théorique](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/known-vendor-issues.html) de 100% entre les différents moteurs de bases (MySQL, Oracle, SQLite ...). 

## Philosophie

[fwk/Db](http://github.com/fwk/Db) a été créé dans un but de simplicité, en ayant [PHP](http://www.php.net) en tête. Il ne s'inspire donc pas des spécifications [Java Persistence API](http://docs.oracle.com/javaee/6/tutorial/doc/bnbpz.html) car bien qu'étant très intéressantes dans un langage fortement typé, leur intérêt est grandement réduit par l'architecture actuelle de PHP (script, sans machine virtuelle, classes et types génériques). De plus, bien souvent, lorsqu'une application devient complexe, les couches d'abstraction sont réduites à leur minimum. L'intérêt devient alors encore moindre et le retour en arrière peut parfois prendre énormément de temps. 

# Contributions

Toute contribution sur [Github](http://github.com) est bienvenue!

* Reportez un bogue: [Issue Tracker](http://github.com/fwk/Db/issues)
* Forkez!

# Crédits Doctrine

```
Copyright (c) 2006-2012 Doctrine Project

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

