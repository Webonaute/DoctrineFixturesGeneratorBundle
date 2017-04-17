<?php

namespace Webonaute\DoctrineFixturesGeneratorBundle\Generator;

use Metadata\ClassMetadata;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Entity
{

    /**
     * @var int
     */
    public $level;

    /**
     * @var string
     */
    public $name;

    /**
     * @var BundleInterface
     */
    public $bundle;

    /**
     * @var ClassMetadata
     */
    public $meta;

}