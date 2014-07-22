<?php

/*
 * This file is part of the Webonaute package.
 *
 * (c) Mathieu Delisle <mdelisle@webonaute.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Generator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Webonaute\DoctrineFixturesGeneratorBundle\Tool\FixtureGenerator;

/**
 * Generates a Doctrine Fixture class based on entity name, ids and custom name.
 *
 * @author Mathieu Delisle <mdelisle@webonaute.ca>
 */
class DoctrineFixtureGenerator extends Generator
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var RegistryInterface
     */
    private $registry;

    /**
     * Constructor
     *
     * @param Filesystem        $filesystem
     * @param RegistryInterface $registry
     */
    public function __construct(Filesystem $filesystem, RegistryInterface $registry)
    {
        $this->filesystem = $filesystem;
        $this->registry = $registry;
    }

    /**
     * Generate Fixture from bundle name, entity name, fixture name and ids
     *
     * @param BundleInterface $bundle
     * @param string          $entity
     * @param string          $name
     * @param array           $ids
     */
    public function generate(BundleInterface $bundle, $entity, $name, array $ids)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager(null)->getConfiguration();
        $config->setEntityNamespaces(
            array_merge(
                array($bundle->getName() => $bundle->getNamespace() . '\\Entity'),
                $config->getEntityNamespaces()
            )
        );

        $fixtureFileName = $this->getFixtureFileName($entity, $name, $ids);

        $entityClass = $this->registry->getAliasNamespace($bundle->getName()) . '\\' . $entity;
        $fixturePath = $bundle->getPath() . '/DataFixtures/ORM/' . $fixtureFileName . '.php';
        $bundleNameSpace = $bundle->getNamespace();
        if (file_exists($fixturePath)) {
            throw new \RuntimeException(sprintf('Fixture "%s" already exists.', $fixtureFileName));
        }

        $class = new ClassMetadataInfo($entityClass);

        $fixtureGenerator = $this->getFixtureGenerator();
        $fixtureGenerator->setFixtureName($fixtureFileName);
        $fixtureGenerator->setBundleNameSpace($bundleNameSpace);
        $fixtureGenerator->setMetadata($class);

        /** @var EntityManager $em */
        $em = $this->registry->getManager();

        $repo = $em->getRepository($class->rootEntityName);
        if (empty($ids)){
            $items = $repo->findAll();
        }else{
            $items = $repo->findById($ids);
        }

        $fixtureGenerator->setItems($items);

        $fixtureCode = $fixtureGenerator->generateFixtureClass($class);

        $this->filesystem->mkdir(dirname($fixturePath));
        file_put_contents($fixturePath, $fixtureCode);

    }

    /**
     * Return fixture file name
     *
     * @param       $entity
     * @param       $name
     * @param array $ids
     *
     * @return string
     */
    public function getFixtureFileName($entity, $name, array $ids)
    {

        $fixtureFileName = "Load";
        //if name params is set.
        if (strlen($name) > 0) {
            $fixtureFileName .= ucfirst($name);
        } else {
            //esle use entity name

            //ids with more than one entry should have --name set.
            if (count($ids) > 1) {
                throw new \RuntimeException('Fixture with multiple IDs should have the --name set.');
            }

            //noBackSlash
            $fixtureFileName .= str_replace('\\', '', $entity);
            if (isset($ids[0])) {
                $fixtureFileName .= $ids[0];
            }
        }

        return $fixtureFileName;
    }

    /**
     * Return the fixture generator object
     *
     * @return FixtureGenerator
     */
    protected function getFixtureGenerator()
    {
        $fixtureGenerator = new FixtureGenerator();
        $fixtureGenerator->setNumSpaces(4);
        return $fixtureGenerator;
    }

}
