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
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Webonaute\DoctrineFixturesGeneratorBundle\Generator\Generator;
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
     * @param Filesystem $filesystem
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
     * @param string $entity
     * @param string $name
     * @param array $ids
     * @param string|null $connectionName
     * @return bool
     */
    public function generate($bundle, $entity, $name, array $ids, $order, $connectionName = null, $overwrite = false, $isFqcnEntity = false, bool $skipEmptyFixture = false)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager($connectionName)->getConfiguration();
        $config->setEntityNamespaces(
            array_merge(
                array('App' . '\\Entity'),
                $config->getEntityNamespaces()
            )
        );

        $fixtureFileName = $this->getFixtureFileName($entity, $name, $ids);
        $entityClass = $this->getFqcnEntityClass($entity, 'App', $isFqcnEntity);

        $fixturePath = $bundle . '/DataFixtures/ORM/Load' . $entity . 'Data.php';
        $bundleNameSpace = $bundle;
        if ($overwrite === false && file_exists($fixturePath)) {
            throw new \RuntimeException(sprintf('Fixture "%s" already exists.', $fixtureFileName));
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->registry->getManager($connectionName);
        $class = $entityManager->getClassMetadata($entityClass);

        $fixtureGenerator = $this->getFixtureGenerator();
        $fixtureGenerator->setFixtureName($fixtureFileName);
        $fixtureGenerator->setBundleNameSpace($bundleNameSpace);
        $fixtureGenerator->setMetadata($class);
        $fixtureGenerator->setFixtureOrder($order);
        $fixtureGenerator->setEntityManager($entityManager);

        /** @var EntityManager $em */
        $em = $this->registry->getManager($connectionName);

        /** @var EntityRepository $repo */
        $repo = $em->getRepository($class->name);
        if (empty($ids)) {
            $items = $repo->findAll();
            $items = array_filter($items, function($item) use ($entityClass){
               if (get_class($item) === $entityClass){
                   return true;
               } else{
                   return false;
               }
            });
        } else {
            $items = $repo->{$this->getFindByIdentifier($class)}($ids);
        }

        $fixtureGenerator->setItems($items);

        //skip fixture who dont have data to import.
        if ($skipEmptyFixture === true && count($items) === 0){
            return false;
        }

        $fixtureCode = $fixtureGenerator->generateFixtureClass();

        $this->filesystem->mkdir(dirname($fixturePath));
        file_put_contents($fixturePath, $fixtureCode);
        return true;
    }

    /**
     * Return the method name to get item by identifier of the entity.
     *
     * @param ClassMetadata $class
     *
     * @return string
     * @throws \Exception
     * @throws \LogicException
     */
    protected function getFindByIdentifier(ClassMetadata $class)
    {
        $identifiers = $class->getIdentifier();
        if (count($identifiers) > 1){
            throw new \Exception("Multiple identifiers is not supported.");
        }

        if (count($identifiers) === 0){
            throw new \LogicException("This entity have no identifier.");
        }

        return "findBy".ucfirst($identifiers[0]);
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

            $fixtureFileName = $this->getFixtureNameFromEntityName($entity, $ids);
        }

        return $fixtureFileName;
    }

    /**
     * Transform Entity name into a compatible filename.
     *
     * @param string $entity
     * @param array $ids
     * @param string $prefix
     * @return string
     */
    public function getFixtureNameFromEntityName(string $entity, array $ids = [], string $prefix = null)
    {
        //noBackSlash
        $name = str_replace('\\', '', $entity);

        //add prefix.
        if (strlen($prefix) > 0) {
            $name = $prefix . ucfirst($name);
        }

        //add first ID in the name.
        if (isset($ids[0])) {
            $name .= $ids[0];
        }

        return $name;
    }

    protected function getFqcnEntityClass($entity, $bundle, $isFqcnEntity = false)
    {
        if ($isFqcnEntity) {
            return $entity;
        } else {
            return $this->registry->getAliasNamespace($bundle) . '\\' . $entity;
        }
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
