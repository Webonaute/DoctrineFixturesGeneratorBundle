<?php
namespace Webonaute\DoctrineFixturesGeneratorBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 */
final class FixtureSnapshot extends Annotation
{
    public $ignore = false;
}