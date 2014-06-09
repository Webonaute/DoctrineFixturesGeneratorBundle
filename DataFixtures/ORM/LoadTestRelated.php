<?php

namespace Webonaute\DoctrineFixturesGeneratorBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Webonaute\DoctrineFixturesGeneratorBundle\Entity\TestRelated;

class LoadTestRelated extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 1; // the order in which fixtures will be loaded
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $testRelated1 = new TestRelated();
        $testRelated1->setName('test');
        $manager->persist($testRelated1);
        $this->addReference('testrelated1', $testRelated1);

        $testRelated2 = new TestRelated();
        $testRelated2->setName('test2');
        $manager->persist($testRelated2);
        $this->addReference('testrelated2', $testRelated2);

        $manager->flush();
    }
}