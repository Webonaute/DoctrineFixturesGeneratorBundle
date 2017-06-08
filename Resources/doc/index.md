Getting Started With DoctrineFixturesGeneratorBundle
==================================

## Prerequisites

This version of the bundle requires Symfony 2.8.x OR 3.x.

## Installation

Installation is a quick (I promise!) 3 step process:

1. Download Webonaute\DoctrineFixturesGeneratorBundle using composer
2. Enable the Bundle
3. Generate your fixtures

### Step 1: Download Webonaute\DoctrineFixturesGeneratorBundle using composer

Add Webonaute\DoctrineFixturesGeneratorBundle by running the command:

``` bash
$ php composer.phar require webonaute/doctrine-fixtures-generator-bundle dev-master
```

Composer will install the bundle to your project's `vendor/Webonaute` directory.

### Step 2: Enable the bundle

Enable the bundle in the kernel:

Be sure to use it only in your dev or test environement.

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    if (in_array($this->getEnvironment(), array('dev', 'test'))) {
        // ...
        $bundles[] = new Webonaute\DoctrineFixturesGeneratorBundle\DoctrineFixturesGeneratorBundle();
        // ...
    }
}
```

### Step 3: Generate your fixture.
``` bash
$ php bin/console doctrine:generate:fixture --entity=Blog:BlogPost --ids="12 534 124" --name="bug43" --order="1"
```

Then edit your new fixture BlogBundle/DataFixture/Orm/LoadBug43.php.

Voila!

## Snapshot

Since version 1.3, you can do a full snapshot of your existing database.

To do so, run this command :
``` bash
php app/console doctrine:generate:fixture --snapshot --overwrite
```
It will create one file per entity you have in your project, it will create it in ```src/<BundleName>/DataFixtures/ORM/Load<<BundleName>Entity<EntityName>.php```

If you have entity relation, the load order will be automatically set according to that.

### Contructor arguments
If your entity constructor requires some arguments they can be described in class annotation:

```php
// src/AppBundle/Entity/MyEntity.php
namespace AppBundle\Entity

use Webonaute\DoctrineFixturesGeneratorBundle\Annotation\ConstructorArguments;

/**
 * @ConstructorArguments(
 *     value={
 *       "article" : {"value"  : "This is string"},
 *     }
 * )
 */
class MyEntity 
{
    public function __constructor(sring $string) {}
}
```
```php
// src/AppBundle/Entity/Comment.php
namespace AppBundle\Entity

use AppBundle\Entity\Article;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation\ConstructorArguments;

/**
 * @ConstructorArguments(
 *     value={
 *       "article" : {"php"  : "new \AppBundle\Entity\Article()"},
 *     }
 * )
 */
class Coment 
{
    public function __constructor(Article $article) {}
}
```

## Property Annotation
You can set a column to not be imported at all into your fixture.
To do so, you can add this annotation to any property of your entity. 
```
@Webonaute\DoctrineFixturesGeneratorBundle\Annotation\Property(ignoreInSnapshot=true)
```

Entity Example :
```
<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation as DFG;

/**
 * AppBundle\Entity\Category
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CategoryRepository")
 * @ORM\Table(name="categories")
 */
class Category
{
    /**
     * @ORM\Id
     * @ORM\Column(name="idcategorie", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="createdAt", type="datetime", options={"default"="0000-00-00 00:00:00"})
     * @DFG\Property(ignoreInSnapshot=true)
     */
    protected $createdAt;

    /**
     * @ORM\Column(name="name", type="text", nullable=true)
     */
    protected $name;
}
```

### Loop in reference object
If you have reference to object in a loop, for exemple, you have an entity category who have a field creator id and a user table with a category id. The ignore annotation can be use to fix the issue on the creator column so the categories fixtures can be created first than the users fixtures. The downside for this is the value of creator will always be null and the column need to have nullable=tue in the annotation.

## FixtureSnapshot Annotation
You can easily ignore an entity from importing the data by adding this annotation to the class doc block.
```
@Webonaute\DoctrineFixturesGeneratorBundle\Annotation\FixtureSnapshot(ignore=true)
```
Example :
```
<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation as DFG;

/**
 * AppBundle\Entity\Category
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CategoryRepository")
 * @ORM\Table(name="categories")
 * @DFG\FixtureSnapshot(ignore=true)
 */
class Category
{
    /**
     * @ORM\Id
     * @ORM\Column(name="idcategorie", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
}
```

# know issues : 
 - vendor entity are not generated yet.
 
