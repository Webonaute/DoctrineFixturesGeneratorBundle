<?php

namespace Webonaute\DoctrineFixturesGeneratorBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Webonaute\DoctrineFixturesGeneratorBundle\Entity\Test;

class LoadTest extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 2; // the order in which fixtures will be loaded
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $test1 = new Test();
        $test1->setName('test');
        $test1->setTestRelated($this->getReference("testrelated1"));
        $manager->persist($test1);
        $this->addReference('test1', $test1);

        $test2 = new Test();
        $test2->setName('test2');
        $test2->setTestRelated($this->getReference("testrelated2"));
        $manager->persist($test2);
        $this->addReference('test2', $test2);

        $manager->flush();
    }
}