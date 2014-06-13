Getting Started With DoctrineFixturesGeneratorBundle
==================================

## Prerequisites

This version of the bundle requires Symfony 2.3+.

## Installation

Installation is a quick (I promise!) 3 step process:

1. Download Webonaute\DoctrineFixturesGeneratorBundle using composer
2. Enable the Bundle
3. Generate your fixtures

### Step 1: Download Webonaute\DoctrineFixturesGeneratorBundle using composer

Add Webonaute\DoctrineFixturesGeneratorBundle by running the command:

``` bash
$ php composer.phar require Webonaute\DoctrineFixturesGeneratorBundle 'dev-master'
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
        $bundles[] = new Webonaute\DoctrineFixturesGeneratorBundle\DoctrineFixturesGeneratorBundle(),
        // ...
    }
}
```

### Step 3: Generate your fixture.
``` bash
$ php bin/console Doctrine:Generate:Fixture --entity=Blog:BlogPost --ids="12 534 124" --name="bug43"
```

Then edit your new fixture BlogBundle/DataFixture/Orm/LoadBug43.php.

Voila!